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

class Service extends \think\Service
{
    /**
     * 服务注册
     * @access public
     * @return void
     */
    public function register()
    {
        // 增加命令
        $this->commands([
            'thinkman' => command\Start::class,
        ]);
    }
}
