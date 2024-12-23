<?php

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class ChatLogModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'chat_log');
    }

    public function GetListByUUID($uuid, $limit)
    {
        return $this->db()
            ->select(null)
            ->where(['uuid' => $uuid])
            ->orderBy('microtime desc')
            ->limit($limit, 0)
            ->fetchAll();
    }
}