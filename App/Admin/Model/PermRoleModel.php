<?php
/**
 * 角色模型
 */

namespace FF\App\Admin\Model;

use FF\Extend\MyModel;

class PermRoleModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_ADMIN, 't_perm_role');
    }
}