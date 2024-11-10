<?php

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;

class ChatLogBll
{
    public function getMsgList($uid, $fUid)
    {
        $uuid = self::makeUUID($uid, $fUid);
        $data = Model::chatLog()->GetListByUUID($uuid);
        $chatLogList = [];
        foreach ($data as $row) {
            $chatLogList[] = [
                'sender' => $row['sender'],
                'receiver' => $row['receiver'],
                'sendTime' => $row['time'],
                'content' => $row['content']
            ];
        }

        return $chatLogList;
    }
    public function ReadAll($uid, $fUid)
    {
        $uuid = self::makeUUID($uid, $fUid);
        Model::chatLog()->update(['status' => 1], ['uuid' => $uuid], 0);
        Bll::friendCache()->updateData($uid, $fUid, ['unReadCnt' => 0]);
        return true;
    }
    public function recordChatLog($uid, $fUid, $content)
    {
        $data = [
            'uuid' => self::makeUUID($uid, $fUid),
            'content' => $content,
            'sender' => $uid,
            'receiver' => $fUid,
            'time' => time(),
            'microtime' => floor(_microtime()),
        ];

        return Model::chatLog()->insert($data);
    }
    public function incUnreadCnt($uid, $fUid)
    {
        Bll::friendCache()->batchUpdateFieldByInc($uid,[$fUid],'unReadCnt');
    }

    public function updateChatTime($uid, $fUid)
    {
        $uuid = self::makeUUID($uid, $fUid);
        $key = Keys::lastChatTime($uuid);
        Dao::redis()->set($key, time(), 86400 * 3);
    }

    public static function makeUUID($from, $to)
    {
        if ($from > $to) {
            return $from . '-' . $to;
        }
        return $to . '-' . $from;
    }

    public function getLastChatTime($uid, $fUid)
    {
        $uuid = self::makeUUID($uid, $fUid);
        $key = Keys::lastChatTime($uuid);
        return Dao::redis()->get($key);
    }

}