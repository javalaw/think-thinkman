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

class Cookie extends \think\Cookie
{
	/**
	 * 保存Cookie
	 * @access public
	 * @param string $name     cookie名称
	 * @param string $value    cookie值
	 * @param int    $expire   cookie过期时间
	 * @param string $path     有效的服务器路径
	 * @param string $domain   有效域名/子域名
	 * @param bool   $secure   是否仅仅通过HTTPS
	 * @param bool   $httponly 仅可通过HTTP访问
	 * @param string $samesite 防止CSRF攻击和用户追踪
	 * @return void
	 */
	protected function saveCookie(string $name, string $value, int $expire, string $path, string $domain, bool $secure, bool $httponly, string $samesite): void
	{
		app('workermanResponse')->cookie($name, $value, null, $path, $domain, $secure, $httponly, $samesite);
	}
}