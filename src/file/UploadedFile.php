<?php
namespace think\thinkman\file;

use think\File;

class UploadedFile extends \think\file\UploadedFile
{
    private $test = false;
    private $originalName;
    private $mimeType;
    private $error;

    public function __construct(string $path, string $originalName, string $mimeType = null, int $error = null, bool $test = false)
    {
        $this->originalName = $originalName;
        $this->mimeType = $mimeType ?: 'application/octet-stream';
        $this->test = $test;
        $this->error = $error ?: UPLOAD_ERR_OK;

        parent::__construct($path, $originalName, $mimeType, $error, $test);
    }
    public function isValid(): bool
    {
        $isOk = UPLOAD_ERR_OK === $this->error;
        return $this->test || $isOk;
    }

    public function move(string $directory, string $name = null): File
    {
        if ($this->isValid()) {
            $target = $this->getTargetFile($directory, $name);

            set_error_handler(function ($type, $msg) use (&$error) {
                $error = $msg;
            });
            $renamed = rename($this->getPathname(), (string) $target);
            restore_error_handler();
            if (!$renamed) {
                throw new FileException(sprintf('Could not move the file "%s" to "%s" (%s)', $this->getPathname(), $target, strip_tags($error)));
            }

            @chmod((string) $target, 0666 & ~umask());

            return $target;
        }

        throw new FileException($this->getErrorMessage());
    }
}