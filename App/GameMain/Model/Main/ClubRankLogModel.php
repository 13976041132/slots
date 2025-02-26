<?php

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class ClubRankLogModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'club_rank_log');
    }

}