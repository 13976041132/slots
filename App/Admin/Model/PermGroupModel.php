<?php
/**
 * 权限组模型
 */

namespace FF\App\Admin\Model;

use FF\Extend\MyModel;

class PermGroupModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_ADMIN, 't_perm_group');
    }
}