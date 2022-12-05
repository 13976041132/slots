<?php
/**
 * CsvReader
 */

namespace FF\Library\Utils;

use FF\Framework\Common\Code;
use FF\Framework\Core\FF;

class CsvReader extends FileReader
{
    private $rowIndex = 0;
    private $header = null;
    protected $headerRowIndex = 1;

    public function close()
    {
        parent::close();

        $this->header = null;
        $this->rowIndex = 0;
    }

    public function readHeader()
    {
        if ($this->header) {
            return $this->header;
        }

        //注意这里先关闭可能存在的已打开文件句柄
        $this->close();
        $this->rowIndex = 0;
        $header = array();

        while (true) {
            $header = $this->readRow();
            $this->rowIndex++;
            if ($this->rowIndex > $this->headerRowIndex) {
                break;
            }

            if ($this->rowIndex !== $this->headerRowIndex) {
                continue;
            }

            break;
        }

        if (!$header) {
            FF::throwException('Failed to read csv header');
        }

        //检查字段是否为空
        foreach ($header as &$value) {
            if ($value === '') {
                FF::throwException(Code::FAILED, 'Csv header contains empty field');
            }
        }

        $this->header = $header;

        return $header;
    }

    public function readRow()
    {
        $handle = $this->getHandle();

        if (feof($handle)) {
            return null;
        }
        if (!$row = fgetcsv($handle)) {
            return null;
        }

        //去除首尾空格
        foreach ($row as &$value) {
            $value = trim($value);
        }

        //对于非首行数据，转换为关联数组
        if ($this->header) {
            $this->rowIndex++;
            if (count($row) != count($this->header)) {
                FF::throwException(Code::FAILED, 'Values is not matched with header on row ' . $this->rowIndex);
            }
            $row = array_combine($this->header, $row);
        }

        return $row;
    }

    public function readByLines($lines)
    {
        $data = array();

        if ($lines <= 0) return $data;

        $this->readHeader();

        while ($lines) {
            if (!$row = $this->readRow()) {
                break;
            }
            $data[] = $row;
            $lines--;
        }

        return $data;
    }

    public function readAll()
    {
        $data = array();

        $this->readHeader();

        while (1) {
            if (!$row = $this->readRow()) {
                break;
            }
            $data[] = $row;
        }

        $this->close();

        return $data;
    }
}