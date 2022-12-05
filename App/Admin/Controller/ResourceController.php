<?php
/**
 * 资源管理类控制器
 */

namespace FF\App\Admin\Controller;

use FF\Factory\Bll;
use FF\Factory\Sdk;
use FF\Framework\Common\Code;
use FF\Framework\Common\Env;
use FF\Framework\Core\FF;

class ResourceController extends BaseController
{
    /**
     * 查看资源
     */
    public function index()
    {
        $data['RES_URL'] = RES_URL;
        $this->display('index.html', $data);
    }

    /**
     * @ignore permission
     */
    public function dirScan()
    {
        $path = (string)$this->getParam('path', false);

        if ($path == '/') $path = '';
        $dir = PATH_RES . $path;
        if (!is_dir($dir)) FF::throwException(Code::PARAMS_INVALID, '目录不存在');

        $items = scandir($dir);

        $dirs = array();
        $files = array();
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            if (is_dir($dir . '/' . $item)) {
                $dirs[] = $item;
            } else {
                $files[] = $item;
            }
        }

        return array(
            'dirs' => $dirs,
            'files' => $files
        );
    }

    /**
     * 创建目录
     */
    public function createDir()
    {
        $path = trim($this->getParam('path'));
        $dir = $this->getParam('dir');

        if (mb_strlen($dir) > 32) {
            FF::throwException(Code::PARAMS_INVALID, '目录名不能超过32个字符');
        }
        if (strpos($dir, '/') !== false) {
            FF::throwException(Code::PARAMS_INVALID, '目录名不能包含特殊字符');
        }
        if (strpos($dir, '\\') !== false) {
            FF::throwException(Code::PARAMS_INVALID, '目录名不能包含特殊字符');
        }

        $key = Sdk::amazonS3()->createDir($path, $dir);

        $result = @mkdir(PATH_RES . '/' . $key);
        if (!$result) {
            FF::throwException(Code::FAILED, '创建目录失败');
        }

        return array(
            'message' => '创建目录成功'
        );
    }

    /**
     * 删除目录
     */
    public function deleteDir()
    {
        $path = trim($this->getParam('path'));

        if (!$path || $path == '/') {
            FF::throwException(Code::PARAMS_INVALID, '删除目录无效');
        }

        $key = Sdk::amazonS3()->deleteDir($path);
        if (!$key) {
            FF::throwException(Code::PARAMS_INVALID, '删除目录失败');
        }

        dir_remove(PATH_RES . '/' . $key);

        return array(
            'message' => '删除目录成功'
        );
    }

    /**
     * 上传资源
     */
    public function upload()
    {
        $path = (string)$this->getParam('path', false);

        if ($path == '/') $path = '';
        $savePath = PATH_RES . $path;
        if (!is_dir($savePath)) FF::throwException(Code::PARAMS_INVALID, '目录不存在');

        if (empty($_FILES)) {
            FF::throwException(Code::PARAMS_INVALID, '没有上传文件');
        }

        foreach ($_FILES as $file) {
            Bll::fileSource()->saveUploadFile($file, $savePath);
        }
        return [];
    }

    /**
     * 删除资源
     */
    public function deleteFile()
    {
        $file = (string)$this->getParam('file');

        if (!file_exists(PATH_RES . $file)) {
            FF::throwException(Code::PARAMS_INVALID, '资源不存在');
        }

        $result = unlink(PATH_RES . $file);
        if (!$result) {
            FF::throwException(Code::PARAMS_INVALID, '资源删除失败');
        }

        Sdk::amazonS3()->deleteObject($file);

        return array(
            'message' => '资源删除成功'
        );
    }
}
