<?php

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class ClubsModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'clubs','clubId');
    }
}