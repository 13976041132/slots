<?php

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class ChatLogModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'chat_log');
    }

    public function GetListByUUID($uuid)
    {
        return $this->db()
            ->select(null)
            ->where(['uuid' => $uuid])
            ->orderBy('microtime desc')
            ->limit(50, 0)
            ->fetchAll();
    }
}