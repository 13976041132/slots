<?php

namespace FF\Library\Utils;

use FF\Framework\Common\Code;
use FF\Framework\Core\FF;

class FileReader
{
    private $file;

    private $handle;

    public function __construct($file)
    {
        $this->file = $file;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        if ($this->handle) {
            fclose($this->handle);
            unset($this->handle);
        }
    }

    public function getFile()
    {
        return $this->file;
    }

    public function getHandle()
    {
        if (!$this->handle) {
            $this->handle = @fopen($this->file, "r");
            if (!$this->handle) {
                FF::throwException(Code::FAILED, 'Failed to open ' . $this->file);
            }
        }

        return $this->handle;
    }

    public function setCursor($position)
    {
        fseek($this->getHandle(), $position);
    }

    public function detectEncoding()
    {
        $this->close();

        $content = fread($this->getHandle(), filesize($this->file));
        $encoding = mb_detect_encoding($content, ['UTF-8', 'GB2312', 'GBK']);

        if ($encoding != 'UTF-8') {
            if ($encoding == 'EUC-CN') $encoding = 'GB2312';
        } else {
            $encoding = 'UTF8';
        }

        $this->close();

        return $encoding;
    }

    public function getTotalLine()
    {
        $count = 0;
        $handle = $this->getHandle();
        while ($content = fgets($handle)) {
            $count++;
        }

        return $count;
    }

    public function readByLines($lines)
    {
        $data = array();

        if ($lines <= 0) return $data;

        $handle = $this->getHandle();

        while ($lines) {
            if (feof($handle)) break;
            $content = trim(fgets($handle));
            if ($content === false) {
                break;
            }
            $data[] = $content;
            $lines--;
        }

        return $data;
    }

    public function readByBytes($bytes)
    {
        $handle = $this->getHandle();

        return fgets($handle, $bytes);
    }
}
