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

use think\exception\Handle;
use think\exception\HttpException;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

class App extends \think\App
{
	/**
	 * Worker实例
	 * @var Worker
	 */
	protected $worker;

	/**
	 * Request实例
	 * @var Request
	 */
	protected $workerRequest;

	/**
	 * Response实例
	 * @var Response
	 */
	protected $workerResponse;

	/**
	 * 处理Worker请求
	 * @param TcpConnection $connection
	 * @param Request $request
	 * @return void
	 */
	public function worker(TcpConnection $connection, Request $request): void
	{
		try {
			// 开始时间
			$this->beginTime = microtime(true);
			// 初始内存占用
			$this->beginMem  = memory_get_usage();
			// 初始化数据库
			$this->db->clearQueryTimes();

			// 兼容 php://input
			$this->workerRequest = $request;
			$this->request->_wmSetRawInput($request->rawBody());

			$this->makeGlobal($connection, $request);

			// 解决第二次刷新识别应用不正确的bug
			$this->http->name('');

			// 逻辑处理 START =====================================================
			while (ob_get_level() > 1) {
				ob_end_clean();
			}

			ob_start();

			$response = $this->http->run();
			$content  = ob_get_clean();

			ob_start();

			$response->send();
			$this->http->end($response);

			$content .= ob_get_clean() ?: '';
			// 逻辑处理 END =====================================================

			$header = [
				'Server' => $this->worker->name,
			];
			foreach ($response->getHeader() as $name => $val) {
				$header[$name] = !is_null($val) ? $val : '';
			}

			$keepAlive = $request->header('connection');
			if (($keepAlive === null && $request->protocolVersion() === '1.1') || strtolower($keepAlive) === 'keep-alive') {
				// 响应
				$response = $this->workerResponse
								->withStatus($response->getCode())
								->withHeaders($header)
								->withBody($content);
				$connection->send($response);
			} else {
				$connection->close($content);
			}

		} catch (HttpException|\Exception|\Throwable $e) {
			$this->exception($connection, $e);
		}
	}

	/**
	 * 是否运行在命令行下
	 * @return bool
	 */
	public function runningInConsole(): bool
	{
		return false;
	}

	/**
	 * 设置worker实例
	 * @param Worker $worker
	 * @return void
	 */
	public function setWorker(Worker $worker)
	{
		$this->worker = $worker;
	}

	/**
	 * 设置worker响应实例
	 * @param Response $response
	 * @return void
	 */
	public function setWorkerResponse(Response $response)
	{
		$this->workerResponse = $response;
	}

	/**
	 * 错误信息发送给前端
	 * @param TcpConnection $connection
	 * @param $e
	 * @return void
	 */
	protected function exception(TcpConnection $connection, $e): void
	{
		$response = function ($code, $content) {
			return $this->workerResponse
				->withStatus($code)
				->withHeaders([
					'Server' => $this->worker->name,
				])
				->withBody($content);
		};

		if ($e instanceof \Exception) {
			$handler = $this->make(Handle::class);
			$handler->report($e);

			$resp    = $handler->render($this->request, $e);
			$content = $resp->getContent();
			$code    = $resp->getCode();

			$connection->send($response($code, $content));
		} else {
			$connection->send($response(500, $e->getMessage()));
		}
	}

	/**
	 * 兼容PHP-FPM框架
	 * @param TcpConnection $connection
	 * @param Request $request
	 * @return void
	 */
	protected function makeGlobal(TcpConnection $connection, Request $request): void
	{
		global $_GET, $_POST, $_COOKIE, $_REQUEST, $_FILES, $_SERVER;

		$_GET     = $request->get();
		$_POST    = $request->post();
		$_COOKIE  = $request->cookie();
		$_REQUEST = [...$_GET, ...$_POST, ...$_COOKIE];
		$_FILES   = $request->file();

		$_SERVER = array_merge($_SERVER, [
			'QUERY_STRING'         => $request->queryString(),
			'REQUEST_METHOD'       => $request->method(),
			'REQUEST_URI'          => $request->uri(),
			'SERVER_PROTOCOL'      => 'HTTP/' . $request->protocolVersion(),
			'SERVER_SOFTWARE'      => $this->worker->name,
			'SERVER_NAME'          => $request->host(true),
			'HTTP_HOST'            => $request->host(),
			'HTTP_USER_AGENT'      => $request->header('user-agent'),
			'HTTP_ACCEPT'          => $request->header('accept'),
			'HTTP_ACCEPT_LANGUAGE' => $request->header('accept-language'),
			'HTTP_ACCEPT_ENCODING' => $request->header('accept-encoding'),
			'HTTP_COOKIE'          => $request->header('cookie'),
			'HTTP_CONNECTION'      => $request->header('connection'),
			'CONTENT_TYPE'         => $request->header('content-type'),
			'REMOTE_ADDR'          => $connection->getRemoteIp(),
			'REMOTE_PORT'          => $connection->getRemotePort(),
			'CONTENT_LENGTH'       => $request->header('content-length'),
			'REQUEST_TIME'         => time()
		]);
	}
}