<?php
/**
 * 用户业务模块
 */

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class UserModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'user', 'uid');
    }

    public function getOne($uid, $fields = null)
    {
        return $this->fetchOne(array('uid' => $uid), $fields);
    }

}