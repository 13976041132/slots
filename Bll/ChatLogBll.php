<?php

namespace FF\Bll;

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
                'sendTime' => date('Y-m-d H:i:s', $row['time']),
                'content' => $row['content']
            ];
        }

        return $chatLogList;
    }
    public function ReadAll($uid, $fUid)
    {
        $uuid = self::makeUUID($uid, $fUid);
        Model::chatLog()->update(['status' => 1], ['uuid' => $uuid], 0);
        Model::friends()->update(['unReadCnt' => 0], ['uid' => $uid, 'fUid' => $fUid]);
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
        Model::friends()->update(['unReadCnt' => ['+=', 1]], ['uid' => $uid, 'fUid' => $fUid]);
    }
    public static function makeUUID($from, $to)
    {
        if ($from > $to) {
            return $from . '-' . $to;
        }
        return $to . '-' . $from;
    }
}