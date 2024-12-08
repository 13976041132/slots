<?php

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class UserRequestLastModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'user_request_last', 'uid');
    }

    public function getRequestId($uid)
    {
        $info = $this->getOneById($uid);
        return $info['requestId'] ?? 0;
    }
}