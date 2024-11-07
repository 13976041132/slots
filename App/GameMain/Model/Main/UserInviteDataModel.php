<?php
/**
 * 用户业务模块
 */

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class UserInviteDataModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'user_invite_data', 'uid');
    }

    public function getOne($where, $fields = null)
    {
        return $this->fetchOne($where, $fields);
    }

    public function updateInviteUid($uid, $inviteUid, $updateTime)
    {
        $sql = "update {$this->table()} 
               set inviteCnt = inviteCnt + 1,
               inviteUids = if(inviteUids,concat(inviteUids,',',{$inviteUid}), $inviteUid) 
               where uid = {$uid} AND updateTime = '{$updateTime}'";

        return $this->db()->query($sql);
    }
}