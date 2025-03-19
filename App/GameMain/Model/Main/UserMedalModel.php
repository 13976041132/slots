<?php

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class UserMedalModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'user_medal', 'uid');
    }

    public function touchData($uid)
    {
        $defData = [
            'medal_num1' => 0, 'medal_num2' => 0,
            'medal_num3' => 0, 'slot_num' => 0
        ];

        $info = $this->getOneById($uid);
        return array_merge($info, $defData);
    }
}