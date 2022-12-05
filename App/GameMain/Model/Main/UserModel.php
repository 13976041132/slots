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
        parent::__construct(DB_MAIN, 't_user', 'uid');
    }

    public function addOne($uid, $nickname, $avatar, $email, $coins)
    {
        $data = array(
            'uid' => $uid,
            'nickname' => $nickname,
            'avatar' => $avatar,
            'email' => $email,
            'coins' => $coins,
            'platformAvatar' => $avatar
        );

        return $this->insert($data);
    }

    public function getOne($uid)
    {
        return $this->fetchOne(array('uid' => $uid));
    }

    public function updateInfo($uid, $data, $where = array())
    {
        $_where = array('uid' => $uid);

        if ($where && is_array($where)) {
            $_where = array_merge($_where, $where);
        }

        return $this->update($data, $_where);
    }
}