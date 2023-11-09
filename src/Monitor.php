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
    protected array $options = [
        // 是否开启文件监控
        'enable' => false,
        // 文件监控检测时间间隔(单位：秒)
        'interval' => 2,
        // 文件监控目录, 默认监控app和config目录
        'paths' => [],
        // 监控的文件扩展名
        'extensions' => [],
        // 最大内存, 进程占用内存达到该数值后自动重启防止内存泄露
        'max_memory' => '128m',
        // 锁文件路径
        'lock_file' => '',
    ];

    /**
     * 文件锁
     * @var string
     */
    protected string $lockFile = '';

    /**
     * 定时器ID
     * @var int
     */
    protected int $timer;

    /**
     * Worker实例
     * @var Worker
     */
    protected Worker $worker;

    /**
     * 内存限制
     * @var int
     */
    protected int $memoryLimit;

    /**
     * 架构函数
     * @access public
     * @param array $options 参数
     * @return void
     */
    public function __construct($options = [])
    {
        // 合并配置
        $this->options = array_replace_recursive($this->options, $options);
        if (!Worker::getAllWorkers()) {
            return;
        }

        $this->makeOptions();
        $this->resume();
        $this->makeWorker();
    }

    /**
     * 重新构建配置
     * @return void 
     */
    protected function makeOptions()
    {
        $this->options['enable'] = isset($this->options['enable']) ? $this->options['enable'] : App::isDebug();
        // 监听间隔
        $this->options['interval'] = $this->options['interval'] ?: 2;
        // 监听目录
        $this->options['paths'] = $this->options['paths'] ?: [App::getAppPath(), App::getConfigPath()];
        // 锁文件
        $this->options['lock_file'] = $this->options['lock_file'] ?: App::getRuntimePath() . 'worker' . DIRECTORY_SEPARATOR . 'thinkman-monitor.lock';
        // 扩展名
        $this->options['extensions'] = $this->options['extensions'] ?: ['php', 'env'];
        // 内存限制
        $this->options['max_memory'] = $this->options['max_memory'] ?: '128m';
        $this->memoryLimit = $this->getMemoryLimit();
    }

    /**
     * 生产worker
     * @return void 
     */
    protected function makeWorker()
    {
        // 实例化worker
        $this->worker = new Worker();
        $this->worker->name = 'thinkman-monitor';
        $this->worker->reloadable = false;
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
        if ($this->options['enable']) {
            $disableFunctions = explode(',', ini_get('disable_functions'));
            if (in_array('exec', $disableFunctions, true)) {
                echo "\nMonitor file change turned off because exec() has been disabled by disable_functions setting in " . PHP_CONFIG_FILE_PATH . "/php.ini\n";
            } else {
                // 监听文件变化
                $this->addTimer($this->options['interval'], [$this, 'checkAllFilesChange']);
                // 监听内存溢出
                $this->addTimer(60, [$this, 'listenMemory']);
            }
        }
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
     * 定时器添加
     * @param int $interval 
     * @param callable $callback 
     * @return void 
     */
    protected function addTimer(int $interval, callable $callback)
    {
        Timer::add($interval, $callback);
    }

    /**
     * 检查所有文件
     * @return bool 
     */
    protected function checkAllFilesChange(): bool
    {
        if ($this->isPaused()) {
            return false;
        }
        $paths = $this->options['paths'];
        foreach ($paths as $path) {
            if ($this->checkFilesChange($path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 监听文件变化
     * @access protected
     * @return void
     */
    protected function checkFilesChange($path): bool
    {
        static $lastMtime, $tooManyFilesCheck;

        if (!$lastMtime) {
            $lastMtime = time();
        }
        clearstatcache();
        if (!is_dir($path)) {
            if (!is_file($path)) {
                return false;
            }
            $iterator = [new \SplFileInfo($path)];
        } else {
            $dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS);
            $iterator = new \RecursiveIteratorIterator($dirIterator);
        }
        $count = 0;
        foreach ($iterator as $file) {
            $count++;
            /** var SplFileInfo $file */
            if (is_dir($file->getRealPath())) {
                continue;
            }
            if (in_array($file->getExtension(), $this->options['extensions'], true) && $lastMtime < $file->getMTime()) {
                $var = 0;
                exec('"' . PHP_BINARY . '" -l ' . $file, $out, $var);
                $lastMtime = $file->getMTime();
                if ($var) {
                    continue;
                }
                // 暂停监控
                $this->pause();
                echo '[monitor:update]' . $file . " update and reload\n";
                if (DIRECTORY_SEPARATOR === '/') {
                    posix_kill(posix_getppid(), SIGUSR1);
                } else {
                    return true;
                }
                break;
            }
        }

        if (!$tooManyFilesCheck && $count > 1000) {
            echo '[monitor]: There are too many files (' . $count . ' files) in ' . $path . ' which makes file monitoring very slow';
            $tooManyFilesCheck = 1;
        }
        return false;
    }

    /**
     * 获取最大内存限制
     * @access protected
     * @return int|float
     */
    protected function getMemoryLimit(): int
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
    protected function listenMemory(int $memoryLimit)
    {
        // 如果暂停了
        if ($this->isPaused() || $memoryLimit <= 0) {
            return;
        }
        $ppid = posix_getppid();
        $childrenFile = "/proc/$ppid/task/$ppid/children";
        if (!is_file($childrenFile) || !($children = file_get_contents($childrenFile))) {
            return;
        }
        foreach (explode(' ', $children) as $pid) {
            $pid = (int)$pid;
            $statusFile = "/proc/$pid/status";
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
    }
}
