<?php
/**
 * 社交好友申请业务模块
 */

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;
use FF\Factory\Model;

class UserDailyFirstLoginLogModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'user_daily_first_login_log');
    }
    public function record($uid)
    {
        $this->insert(['uid' => $uid, 'date' => date('Y-m-d')], true);
    }
}