<?php

namespace FF\Bll;

use FF\Constants\Exceptions;
use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Framework\Core\FF;

class ChatLogBll
{
    public function getMsgList($uid, $fUid, $limit = 50)
    {
        $uuid = self::makeUUID($uid, $fUid);
        $data = Model::chatLog()->GetListByUUID($uuid, $limit);
        $chatLogList = [];
        foreach ($data as $row) {
            $chatLogList[] = [
                'id' => $row['id'],
                'status' => $row['status'],
                'sender' => $row['sender'],
                'receiver' => $row['receiver'],
                'sendTime' => $row['time'],
                'content' => $row['content'],
                'microtime' => $row['microtime'],
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

    public function checkChatContent($content)
    {
        if(!is_string($content)) {
            FF::throwException(Exceptions::RET_CHAT_CONTENT_INVALID, 'chat content invalid');
        }

        if (empty($content)) {
            FF::throwException(Exceptions::RET_CHAT_CONTENT_EMPTY, 'chat content empty');
        }

        if (strlen($content) > 256) {
            FF::throwException(Exceptions::RET_CHAT_CONTENT_TOO_LONG, 'chat content too long');
        }

        //判断聊天是否有表情
        if (preg_match('/[\x{1F600}-\x{1F64F}]/u', $content)) {
            FF::throwException(Exceptions::RET_CHAT_CONTENT_HAS_EMOJI, 'chat content has emoji');
        }
    }
}