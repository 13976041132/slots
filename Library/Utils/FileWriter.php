<?php
/**
 * 文件写入器
 */

namespace FF\Library\Utils;

class FileWriter
{
    private $file;

    private $mode;

    private $handle;

    public function __construct($file, $mode = 'a')
    {
        $this->file = $file;
        $this->mode = $mode;
    }

    public function __destruct()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }

    public function getFile()
    {
        return $this->file;
    }

    public function getHandle()
    {
        if (!$this->handle) {
            $this->handle = @fopen($this->file, $this->mode);
        }

        return $this->handle;
    }

    public function write($content)
    {
        fwrite($this->getHandle(), $content);
    }
}