<?php

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class ClubPublishHelpDataModel extends MyModel
{
    const PUBLISH_HELP_STATUS_ING = 0;
    const PUBLISH_HELP_STATUS_FINISH = 1;
    const PUBLISH_HELP_STATUS_FAIL = 2;

    public function __construct()
    {
        parent::__construct(DB_MAIN, 'club_publish_help_data','publishId');
    }
}