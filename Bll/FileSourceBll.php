<?php
/**
 * 文件资源业务逻辑
 */

namespace FF\Bll;

use FF\Framework\Common\Code;
use FF\Framework\Core\FF;

class FileSourceBll
{
    public function saveUploadFile($file, $savePath = null)
    {
        if (!is_array($file) || empty($file['name']) || empty($file['tmp_name'])) {
            FF::throwException(Code::FAILED, '上传文件无效');
        }

        $filename = $file['name'];
        $fileInfo = explode('.', $filename);
        $ext = array_pop($fileInfo);

        //初始化文件保存目录
        if (!$savePath) {
            $savePath = PATH_ROOT . '/Upload/' . date('Ym');
        }
        if (!is_dir($savePath)) {
            $result = mkdir($savePath, 0777, true);
            if (!$result) {
                FF::throwException(Code::FAILED, error_get_last()['message']);
            }
        }

        $suffix = 0;
        $saveName = $filename;
        $originName = substr($filename, 0, -strlen($ext) - 1);
        $uuid = uniqid(mt_rand());

        //保证文件名不重复
        while (file_exists($savePath . '/' . $saveName)) {
            $suffix += 1;
            $saveName = $originName . '_' . $suffix . '.' . $ext;
        }

        $result = move_uploaded_file($file['tmp_name'], $savePath . '/' . $saveName);

        if (!$result) {
            FF::throwException(Code::FAILED, '上传文件保存失败:' . error_get_last()['message']);
        }

        return array(
            'uuid' => $uuid,
            'filename' => $filename,
            'savePath' => $savePath,
            'saveName' => $saveName,
        );
    }
}