<?php

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class UserBllRewardDataModel extends MyModel
{
    const STATUS_NON_AWARD = 0;
    const STATUS_AWARD = 1;
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'user_bll_reward_data');
    }

    public function record($uid, $triggerUid, $msgId, $itemList, $duration = 365 * 86400)
    {
        $data = [
            'uid' => $uid,
            'messageId' => $msgId,
            'triggerUid' => $triggerUid,
            'time' => time(),
            'expireTime' => time() + $duration,
            'itemList' => json_encode($itemList)
        ];
        return $this->insert($data);
    }

    public function getCount($where)
    {
        $info = $this->fetchOne($where, 'count(1) count');
        return $info['count'] ?? 0;
    }

}