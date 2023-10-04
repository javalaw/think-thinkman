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

class Response extends \Workerman\Protocols\Http\Response
{
	/**
     * 动态设置响应头
     * @access public
	 * @param array $headers
	 * @param bool $merge
	 * @return $this
     */
	public function withHeaders($headers, bool $merge = false)
	{
		if ($merge) {
			parent::withHeaders($headers);
		}
		else {
			$this->_header = $headers;
		}

		return $this;
	}
}