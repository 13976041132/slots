<?php

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class ClubPublishHelpDataModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'club_publish_help_data','id');
    }
}