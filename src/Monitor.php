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
		// 监控间隔时间
		'interval' => 2,
		// 监控目录
		'paths' => [],
	];

	/**
     * 配置参数
     * @var bool
     */
	protected $stop = false;

	/**
     * 配置参数
     * @var Timer
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
		// 实例化worker
		$this->worker = new Worker();
		$this->worker->name = 'thinkman-monitor';
		$this->worker->reloadable = false;
		$this->worker->user = 'root';
		$this->worker->onWorkerStart = [$this, 'onWorkerStart'];
	}

	/**
     * 启动回调
     * @access public
     * @param array $options 参数
	 * @return void
     */
	public function onWorkerStart()
	{
		// 监听间隔
		$interval = $this->options['interval'];
		if(empty($interval)){
			$interval = 2;
		}

		// 监听目录
        $paths = $this->options['paths'];
		if(empty($paths)){
			$paths = [App::getAppPath(), App::getConfigPath()];
		}

		// 定时器
		$this->timer = Timer::add($interval, function () {
			// 如果停止
			if ($this->stop) {
				Timer::del($this->timer);
				return;
			}

			foreach ($paths as $path){

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
}