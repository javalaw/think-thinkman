<?php
// +----------------------------------------------------------------------
// | ThinkMan [A Simple Web Framework For ThinkPHP]
// +----------------------------------------------------------------------
// | ThinkMan
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: axguowen <axguowen@qq.com>
// +----------------------------------------------------------------------

namespace think\thinkman;

use think\facade\App;
use Workerman\Timer;
use Workerman\Worker;

class Monitor
{
    /**
     * 配置参数
     * @var array
     */
    protected $options = [
        // 是否开启文件监控
        'enable' => false,
        // 文件监控检测时间间隔(单位：秒)
        'interval' => 2,
        // 文件监控目录, 默认监控app和config目录
        'paths' => [],
        // 最大内存, 进程占用内存达到该数值后自动重启防止内存泄露
        'max_memory' => '128m',
        // 锁文件路径
        'lock_file' => '',
    ];

    /**
     * 配置参数
     * @var bool
     */
    protected $stop = false;

    /**
     * 文件锁
     * @var string
     */
    protected $lockFile = '';

    /**
     * 定时器ID
     * @var int
     */
    protected $timer;

    /**
     * Worker实例
     * @var Worker
     */
    protected $worker;

    /**
     * 架构函数
     * @access public
     * @param array $options 参数
     * @return void
     */
    public function __construct($options = [])
    {
        // 合并配置
        $this->options = array_merge($this->options, $options);
        // 如果锁文件为空
        if (empty($this->options['lock_file'])) {
            $this->options['lock_file'] = App::getRuntimePath() . 'worker' . DIRECTORY_SEPARATOR . 'thinkman-monitor.lock';
        }
        // 实例化worker
        $this->worker = new Worker();
        $this->worker->name = 'thinkman-monitor';
        $this->worker->reloadable = false;
//        $this->worker->user = 'root';
        $this->worker->onWorkerStart = [$this, 'onWorkerStart'];
    }

    /**
     * 启动回调
     * @access public
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStart(Worker $worker)
    {
        // 调试模式或者开启文件监控
        if (App::isDebug() || true == $this->options['enable']) {
            // 监听文件变化
            $this->listenFilesChange();
        }

        // 监听内存溢出
        $this->listenMemory();
    }

    /**
     * 暂停监控
     * @access protected
     * @return void
     */
    protected function pause(): void
    {
        file_put_contents($this->options['lock_file'], time());
    }

    /**
     * 继续监控
     * @access protected
     * @return void
     */
    protected function resume(): void
    {
        clearstatcache();
        if (is_file($this->options['lock_file'])) {
            unlink($this->options['lock_file']);
        }
    }

    /**
     * 监控是否已暂停
     * @access protected
     * @return bool
     */
    protected function isPaused(): bool
    {
        clearstatcache();
        return file_exists($this->options['lock_file']);
    }

    /**
     * 监听文件变化
     * @access protected
     * @return void
     */
    protected function listenFilesChange(): void
    {
        if ($this->isPaused()) {
            return;
        }

        // 监听间隔
        $interval = $this->options['interval'];
        if (empty($interval)) {
            $interval = 2;
        }

        // 监听目录
        $paths = $this->options['paths'];
        if (empty($paths)) {
            $paths = [App::getAppPath(), App::getConfigPath()];
        }

        // 定时器
        $this->timer = Timer::add($interval, function () use ($paths) {
            // 如果停止
            if ($this->stop) {
                Timer::del($this->timer);
                return;
            }

            foreach ($paths as $path) {

                static $lastMtime, $tooManyFilesCheck;

                if (!$lastMtime) {
                    $lastMtime = time();
                }

                clearstatcache();

                if (!is_dir($path)) {
                    if (!is_file($path)) {
                        break;
                    }
                    $iterator = [new \SplFileInfo($path)];
                } else {
                    $dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS);
                    $iterator = new \RecursiveIteratorIterator($dirIterator);
                }

                $count = 0;

                foreach ($iterator as $file) {
                    $count++;
                    if (in_array($file->getExtension(), ['php', 'env'], true) && $lastMtime < $file->getMTime()) {
                        // 暂停监控
                        $this->pause();

                        if (!posix_kill(posix_getppid(), SIGUSR1)) {
                            echo '[monitor] require root user';
                            $this->stop = true;
                            break;
                        } else {
                            echo '[monitor:update] ' . $file;
                        }

                        $lastMtime = $file->getMTime();
                        break;
                    }
                }

                if (!$tooManyFilesCheck && $count > 1000) {
                    echo '[monitor]: There are too many files (' . $count . ' files) in ' . $path . ' which makes file monitoring very slow';
                    $tooManyFilesCheck = 1;
                }
            }
        });
    }

    /**
     * 获取最大内存限制
     * @access protected
     * @return int|float
     */
    protected function getMemoryLimit()
    {
        $usePhpIni = false;

        $memoryLimit = $this->options['memory_limit'];
        if ($memoryLimit === 0) {
            return 0;
        }

        if (empty($memoryLimit)) {
            $memoryLimit = ini_get('memory_limit');
            $usePhpIni = true;
        }

        if ($memoryLimit == -1) {
            return 0;
        }

        $unit = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        if ($unit === 'g') {
            $memoryLimit = 1024 * (int)$memoryLimit;
        } else if ($unit === 'm') {
            $memoryLimit = (int)$memoryLimit;
        } else if ($unit === 'k') {
            $memoryLimit = ((int)$memoryLimit / 1024);
        } else {
            $memoryLimit = ((int)$memoryLimit / (1024 * 1024));
        }
        if ($memoryLimit < 30) {
            $memoryLimit = 30;
        }
        if ($usePhpIni) {
            $memoryLimit = (int)(0.8 * $memoryLimit);
        }
        return $memoryLimit;
    }

    /**
     * 监听内存泄露
     * @access protected
     * @param int|float $memoryLimit
     * @return void
     */
    protected function listenMemory()
    {
        // 超出最大进程内存限制重启进程
        $memoryLimit = $this->getMemoryLimit();
        if (empty($memoryLimit)) {
            return;
        }

        Timer::add(60, function () use ($memoryLimit) {
            // 如果暂停了
            if ($this->isPaused() || $memoryLimit <= 0) {
                return;
            }
            $ppid = posix_getppid();
            $childrenFile = '/proc/' . $ppid . '/task/' . $ppid . '/children';
            if (!is_file($childrenFile) || !($children = file_get_contents($childrenFile))) {
                return;
            }
            foreach (explode(' ', $children) as $pid) {
                $pid = (int)$pid;
                $statusFile = '/proc/' . $pid . '/status';
                if (!is_file($statusFile) || !($status = file_get_contents($statusFile))) {
                    continue;
                }
                $mem = 0;
                if (preg_match('/VmRSS\s*?:\s*?(\d+?)\s*?kB/', $status, $match)) {
                    $mem = $match[1];
                }
                $mem = (int)($mem / 1024);
                if ($mem >= $memoryLimit) {
                    // 暂停监控
                    $this->pause();
                    posix_kill($pid, SIGINT);
                }
            }
        });
    }
}
