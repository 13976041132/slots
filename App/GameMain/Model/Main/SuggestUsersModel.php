<?php
/**
 * 社交好友申请业务模块
 */

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class SuggestUsersModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'suggest_users');
    }
}