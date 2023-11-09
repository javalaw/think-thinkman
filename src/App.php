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

use think\exception\FuncNotFoundException;
use ReflectionException;
use InvalidArgumentException;
use think\exception\ClassNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\helper\Arr;
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
    public function worker(TcpConnection $connection, Request $rawRequest): void
    {
        $this->instance(TcpConnection::class, $connection);
        try {
            // 开始时间
            $this->beginTime = microtime(true);
            // 初始内存占用
            $this->beginMem = memory_get_usage();
            // 初始化数据库
            $this->db->clearQueryTimes();

            // 兼容 php://input
            $request = $this->prepareRequest($connection, $rawRequest);

            // 解决第二次刷新识别应用不正确的bug
            $this->http->name('');

            // 逻辑处理 START =====================================================
            $level = ob_get_level();
            ob_start();

            $response = $this->http->run($request);
            $content = $response->getContent();
            if (ob_get_level() == 0) {
                ob_start();
            }
            $this->http->end($response);
            if (ob_get_length() > 0) {
                $response->content(ob_get_contents() . $content);
            }
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            // 逻辑处理 END =====================================================

            $header = [
                'Server' => $this->worker->name,
            ];
            foreach ($response->getHeader() as $name => $val) {
                $header[$name] = !is_null($val) ? $val : '';
            }

            $keepAlive = $request->header('connection');
            if (($keepAlive === null && $rawRequest->protocolVersion() === '1.1') || strtolower($keepAlive) === 'keep-alive') {
                // 响应
                $response = $this->workerResponse
                    ->withStatus($response->getCode())
                    ->withHeaders($header)
                    ->withBody($response->getContent());
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

            $resp = $handler->render($this->request, $e);
            $content = $resp->getContent();
            $code = $resp->getCode();

            $connection->send($response($code, $content));
        } else {
            $connection->send($response(500, $e->getMessage()));
        }
    }

    /**
     * prepare think Request
     * @param TcpConnection $connection connection
     * @param Request $rawRequest workman request
     * @return \think\Request 
     * @throws FuncNotFoundException 
     * @throws ReflectionException 
     * @throws InvalidArgumentException 
     * @throws ClassNotFoundException 
     */
    protected function prepareRequest(TcpConnection $connection, Request $rawRequest)
    {
        $header = $rawRequest->header() ?: [];
        $servers = [];
        foreach($header as $key => $val) {
            $servers['http_' . str_replace('-', '_', strtolower($key))] = $val;
        }
        $servers = [
            'query_string' => $rawRequest->queryString(),
            'request_method' => $rawRequest->method(),
            'request_uri' => $rawRequest->path(),
            'server_protocol' => 'HTTP/' . $rawRequest->protocolVersion(),
            'server_software' => $this->worker->name,
            'server_name' => $rawRequest->host(true),
            'http_host' => $rawRequest->host(),
            'http_user_agent' => $rawRequest->header('user-agent'),
            'http_accept' => $rawRequest->header('accept'),
            'http_accept_language' => $rawRequest->header('accept-language'),
            'http_accept_encoding' => $rawRequest->header('accept-encoding'),
            'http_cookie' => $rawRequest->header('cookie'),
            'http_connection' => $rawRequest->header('connection'),
            'content_type' => $rawRequest->header('content-type'),
            'remote_addr' => $connection->getRemoteIp(),
            'remote_port' => $connection->getRemotePort(),
            'content_length' => $rawRequest->header('content-length'),
            'request_time' => time(),
            ...$servers,
        ];
        $request = $this->make(\think\Request::class, [], true);
        $pathInfo = ltrim($rawRequest->path(), '/');
        $infos = explode('/', $pathInfo);
        if($infos[0] && $infos[0] == 'index.php') {
            $pathInfo = ltrim(substr($pathInfo, strlen('index.php')), '/');
        }
        $request->withHeader($header)
            ->withServer($servers)
            ->withGet($rawRequest->get() ?: [])
            ->withPost($rawRequest->post() ?: [])
            ->withRequest([...$rawRequest->get(), ...$rawRequest->post()])
            ->withCookie($rawRequest->cookie() ?: [])
            ->withFiles($this->getFiles($rawRequest))
            ->withInput($rawRequest->rawBody())
            ->setBaseUrl($rawRequest->path())
            ->setUrl($rawRequest->uri())
            ->setPathinfo($pathInfo);
        return $request;
    }

    /**
     * get files from workerman request
     * @param Request $rawRequest raw request
     * @return array<array-key, mixed> 
     */
    protected function getFiles(Request $rawRequest)
    {
        if (empty($rawRequest->file())) {
            return [];
        }

        return array_map(function ($file) {
            if (!Arr::isAssoc($file)) {
                $files = [];
                foreach ($file as $f) {
                    $files['name'][] = $f['name'];
                    $files['type'][] = $f['type'];
                    $files['tmp_name'][] = $f['tmp_name'];
                    $files['error'][] = $f['error'];
                    $files['size'][] = $f['size'];
                }
                return $files;
            }
            return $file;
        }, $rawRequest->file());
    }
}
