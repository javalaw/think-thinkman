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

class Request extends \think\Request
{
	public function _wmSetRawInput($input)
	{
		$this->input = $input;
	}

	public function getInput(): string
	{
		return app('workermanRequest')->rawBody();
	}
}