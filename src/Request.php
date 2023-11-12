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
use think\thinkman\traits\InteractWithProperty;
use think\thinkman\file\UploadedFile;
use think\file\UploadedFile as ThinkUploadedFile;

class Request extends \think\Request
{
    use InteractWithProperty;
    public function withRequest(array $request)
    {
        $this->request = $request;
        return $this;
    }

    protected function dealUploadFile(array $files, string $name): array
    {
        $result = parent::dealUploadFile($files, $name);
        return $this->convertUploadedFileType($result);
    }

    protected function convertUploadedFileType(array $files): array
    {
        $properties = ['mimeType', 'error', 'test', 'originalName'];
        foreach ($files as $key => $item) {
            if (is_array($item)) {
                $files[$key] = $this->convertUploadedFileType($item);
            }
            if($item instanceof ThinkUploadedFile) {
                $originProperties = $this->readProperty($item, $properties);
                $files[$key] = new UploadedFile($item->getPathname(), $originProperties['originalName'], $originProperties['mimeType'], $originProperties['error'], $originProperties['test']);
            }
        }
        return $files;
    }
}
