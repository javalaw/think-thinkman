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

use think\facade\App as FacadeApp;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkerRequest;
use Workerman\Worker;

class ThinkMan
{
	/**
     * 配置参数
     * @var array
     */
	protected $options = [
		// 监听地址
		'host' => '0.0.0.0',
		// 监听端口
		'port' => 1246,
		// 上下文
		'context' => [],
		// 是否以守护进程启动
		'daemonize' => false,
		// 内容输出文件路径
		'stdout_file' => '',
		// pid文件路径
		'pid_file' => '',
		// 日志文件路径
		'log_file' => '',
		// 最大请求数, 进程接收到该数量的请求后自动重启防止内存泄露
		'request_limit' => 10000,
		// 静态文件支持
		'static_support' => false,
		// 文件监控配置(仅Linux下有效)
		'file_monitor' => [
			// 是否开启文件监控
			'enable' => false,
			// 文件监控检测时间间隔(单位：秒)
			'interval' => 2,
			// 文件监控目录, 默认监控app和config目录
			'paths' => [],
			// 最大内存, 进程占用内存达到该数值后自动重启防止内存泄露
			'memory_limit' => '128m',
		],
		// Worker配置
		'worker' => []
	];

	/**
     * 应用容器实例
     * @var \think\App
     */
	protected $app;

	/**
	 * 应用根目录
	 * @var string
	 */
	protected $rootPath;

	/**
	 * web访问目录
	 * @var string
	 */
	protected $publicPath;

	/**
	 * Worker实例
	 * @var Worker
	 */
	protected $worker;

	/**
	 * worker回调方法
	 * @var array
	 */
	protected $events = ['onWorkerStart', 'onConnect', 'onMessage', 'onClose', 'onError', 'onBufferFull', 'onBufferDrain', 'onWorkerReload', 'onWebSocketConnect'];

	/**
     * 架构函数
     * @access public
     * @param array $options 参数
	 * @return void
     */
	public function __construct(array $options = [])
	{
		// 合并配置
		$this->options = array_merge($this->options, $options);
		// 实例化worker
		$this->worker = new Worker('http://' . $this->options['host'] . ':' . $this->options['port'], $this->options['context']);
		// 初始化
		$this->init();
	}

	/**
     * 初始化
     * @access protected
	 * @return void
     */
	protected function init()
	{
		// 可配置的worker属性
		$workerPropertyMap = [
			'name',
			'count',
			'user',
			'group',
			'reloadable',
			'reusePort',
			'transport',
			'protocol',
		];
		foreach ($workerPropertyMap as $property) {
			if (isset($this->options['worker'][$property])) {
				$this->worker->$property = $this->options['worker'][$property];
			}
		}
		// 如果名称为空
		if(empty($this->worker->name) || 'none' == $this->worker->name){
			$this->worker->name = 'thinkman';
		}

		// 内容输出文件路径
		if(!empty($this->options['stdout_file'])){
			// 目录不存在则自动创建
			$stdout_dir = dirname($this->options['stdout_file']);
			if (!is_dir($stdout_dir)){
				mkdir($stdout_dir);
			}
			// 指定stdout文件路径
			Worker::$stdoutFile = $this->options['stdout_file'];
		}

		// pid文件路径
		if(empty($this->options['pid_file'])){
			$this->options['pid_file'] = FacadeApp::getRuntimePath() . 'worker' . DIRECTORY_SEPARATOR . $this->worker->name . '.pid';
		}
		// 目录不存在则自动创建
		$pid_dir = dirname($this->options['pid_file']);
		if (!is_dir($pid_dir)){
			mkdir($pid_dir);
		}
		// 指定pid文件路径
		Worker::$pidFile = $this->options['pid_file'];
		
		// 日志文件路径
		if(empty($this->options['log_file'])){
			$this->options['log_file'] = FacadeApp::getRuntimePath() . 'worker' . DIRECTORY_SEPARATOR . $this->worker->name . '.log';
		}
		// 目录不存在则自动创建
		$log_dir = dirname($this->options['log_file']);
		if (!is_dir($log_dir)){
			mkdir($log_dir);
		}
		// 指定日志文件路径
		Worker::$logFile = $this->options['log_file'];

		// 守护进程启动
		if (true === $this->options['daemonize']) {
            Worker::$daemonize = true;
        }
	}

	/**
     * 设置根路径
     * @access public
	 * @param string $path
	 * @return $this
     */
	public function setRootPath($path)
    {
        $this->rootPath = $path;
		return $this;
    }

	/**
     * 设置web访问目录
     * @access public
	 * @param string $path
	 * @return $this
     */
	public function setPublicPath($path)
    {
        $this->publicPath = $path;
		return $this;
    }

	/**
     * 启动
     * @access public
	 * @return void
     */
	public function start()
	{
		// 如果根路径为空
		if(empty($this->rootPath)){
			// 设置根路径
			$this->rootPath = FacadeApp::getRootPath();
		}
		// 如果公共访问路径为空
		if(empty($this->publicPath)){
			// 设置公共访问路径
			$this->publicPath = FacadeApp::getRootPath() . 'public';
		}
		
		// 设置回调
        foreach ($this->events as $event) {
            if (method_exists($this, $event)) {
                $this->worker->$event = [$this, $event];
            }
        }

		// 开启监控
		if (DIRECTORY_SEPARATOR !== '\\') {
			$monitor = new Monitor($this->options['file_monitor']);
		}

		Worker::runAll();
	}

	/**
     * 停止
     * @access public
     * @return void
     */
    public function stop()
    {
        Worker::stopAll();
    }

	/**
     * 启动回调
     * @access public
	 * @param Worker $worker
	 * @return void
     */
	public function onWorkerStart(Worker $worker): void
	{
		// 实例化应用容器
		$this->app = new App($this->rootPath);
		// 初始化
		$this->app->initialize();
		// 设置worker实例
		$this->app->setWorker($worker);
		// 设置响应实例
		$this->app->setWorkerResponse(app(Response::class));
		// 容器绑定
		$this->app->bind([
			'think\Cookie' => Cookie::class,
			'think\Request' => Request::class
		]);
	}

	/**
     * 接收请求回调
     * @access public
	 * @param TcpConnection $connection
	 * @param WorkerRequest $request
	 * @return void
     */
	public function onMessage(TcpConnection $connection, WorkerRequest $request): void
	{
		// 访问资源文件
		$file = $this->publicPath . DIRECTORY_SEPARATOR . $request->uri();
		// 启用静态文件支持且文件存在
		if ($this->options['static_support'] && is_file($file)) {
			// 检查if-modified-since头判断文件是否修改过
			if (!empty($if_modified_since = $request->header('if-modified-since'))) {
				$modified_time = date('D, d M Y H:i:s', filemtime($file)) . ' ' . \date_default_timezone_get();
				// 文件未修改则返回304
				if ($modified_time === $if_modified_since) {
					$connection->send(new \Workerman\Protocols\Http\Response(304));
					return;
				}
			}

			// 文件修改过或者没有if-modified-since头则发送文件
			$response = (new \Workerman\Protocols\Http\Response(200, [
				'Server' => $this->worker->name,
			]))->withFile($file);
			$connection->send($response);
		}
		// 执行app逻辑
		else {
			$this->app->worker($connection, $request);
		}

		// 请求一定数量后，退出进程重开，防止内存溢出
		static $requestCount;
		if (++$requestCount > $this->options['request_limit']) {
			Worker::stopAll();
		}
	}

	public function __set($name, $value)
    {
        $this->worker->$name = $value;
    }

    public function __call($method, $args)
    {
        call_user_func_array([$this->worker, $method], $args);
    }
}