<?php
/**
 * 管理日志模块
 */

namespace FF\App\Admin\Model;

use FF\Extend\MyModel;
use FF\Factory\Bll;
use FF\Factory\Model;

class OperationLogModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_LOG, 't_operation_log');
    }

    /*
     * 添加日志
     */
    public function addLog($category, $target, $action, $content = '')
    {
        $user = Bll::session()->get('account');

        $logData = [];
        $logData['appId'] = APP_ID;
        $logData['time'] = now();

        $logData['category'] = $category;
        $logData['target'] = $target;
        $logData['action'] = $action;
        $logData['user'] = $user ?: 'System';
        $logData['ip'] = is_cli() ? '127.0.0.1' : get_ip();
        $logData['content'] = $content;

        // 添加 target 数据
        Model::operationTarget()->insert(array(
            'category' => $category,
            'target' => $target,
        ));

        $this->insert($logData);
    }
}