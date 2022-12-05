<?php
/**
 * 配置管理控制器
 */

namespace FF\App\Admin\Controller;

use FF\Factory\Bll;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;

class ConfigController extends BaseController
{
    /**
     * 配置总览
     */
    public function index()
    {
        $data['configFiles'] = Config::get('tables-csv');

        $this->display('index.html', $data);
    }

    /**
     * 查看转盘配置
     */
    public function wheel()
    {
        $data = array();
        $data['wheels'] = Config::get('feature/wheels');

        $this->display('wheel.html', $data);
    }

    /**
     * 查看转盘奖励配置
     */
    public function wheelItems()
    {
        $wheelId = $this->getParam('wheelId');

        $data = array();
        $data['items'] = Config::get('feature/wheel-items', $wheelId);

        $this->display('wheelItems.html', $data);
    }

    /**
     * 上传配置表
     */
    public function uploadConfigFile()
    {
        if (FF::isProduct()) {
            FF::throwException(Code::FAILED, '生产环境不能上传配置表');
        }

        $table = $this->getParam('table');

        if (empty($_FILES)) {
            FF::throwException(Code::PARAMS_INVALID, '没有上传文件');
        }

        $csvFile = null;

        $tables = Config::get('tables-csv');
        foreach ($tables as $groupFiles) {
            if (isset($groupFiles[$table])) {
                $csvFile = $groupFiles[$table];
            }
        }

        if (!$csvFile) {
            FF::throwException(Code::FAILED, '不支持该配置表');
        }

        $file = array_values($_FILES)[0];
        if ($csvFile != $file['name']) {
            FF::throwException(Code::FAILED, '配置表文件名称错误');
        }

        $file = Bll::fileSource()->saveUploadFile($file);
        $sourceFile = $file['savePath'] . '/' . $file['saveName'];

        Bll::config()->initConfigFromFile($table, $sourceFile, '', true);
        if (!FF::isProduct()) {
            Bll::server()->reloadAllServer();
        }
        return [$file];
    }

    /**
     * 刷新配置
     */
    public function refresh()
    {
        $env = ENV;
        $md5File = PATH_ROOT . "/Config/tables-csv-md5-{$env}.php";
        if (file_exists($md5File)) {
            unlink($md5File);
        }

        exec_php_file(PATH_ROOT . '/Doc/database/import.php');

        return array(
            'message' => '配置已刷新'
        );
    }
}