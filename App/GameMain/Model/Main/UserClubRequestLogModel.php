<?php

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class UserClubRequestLogModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'user_club_request_log');
    }

    public function addRequest($uid ,$clubId, $invitedBy)
    {
        $time = now();
        $update = "`requestTime` = '{$time}', `status` = 1";
        $sql = "INSERT INTO {$this->table()} VALUES ({$clubId}, {$uid}, {$invitedBy}, 1, '{$time}') ON DUPLICATE KEY UPDATE {$update}";

        return $this->db()->query($sql);
    }
}