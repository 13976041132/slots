<?php

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class ClubChatLogModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'club_chat_log');
    }

}