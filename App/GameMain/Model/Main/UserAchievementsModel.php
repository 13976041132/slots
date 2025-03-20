<?php

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class UserAchievementsModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'user_achievements', 'uid');
    }

    public function touchData($uid)
    {
        $defData = [
            'spinTimes' => 0, 'bigWinTimes' => 0,
            'megaWinTimes' => 0, 'jackpotTimes' => 0
        ];

        $info = $this->getOneById($uid);
        return array_merge($defData, $info);
    }
}