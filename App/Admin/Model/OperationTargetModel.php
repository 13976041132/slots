<?php
/**
 * 管理日志对象模块
 */

namespace FF\App\Admin\Model;

use FF\Extend\MyModel;

class OperationTargetModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_ADMIN, 't_operation_target');
    }
}