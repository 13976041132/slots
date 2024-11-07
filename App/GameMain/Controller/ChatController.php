<?php

namespace FF\App\GameMain\Controller;

use FF\Constants\Exceptions;
use FF\Factory\Bll;
use FF\Framework\Core\FF;

class ChatController extends BaseController
{
    public function sendMessage()
    {
        $uid = $this->getUid();
        $fUid = $this->getParam('fUid');
        $content = (string)$this->getParam('content');
        if ($uid == $fUid) {
            FF::throwException(Exceptions::RET_CHAT_DENY_SEND_MYSELF);
        }

        if (!Bll::friends()->isMyFriend($uid, $fUid)) {
            FF::throwException(Exceptions::RET_CHAT_NOT_FRIEND_ERROR);
        }
        if (!Bll::chatLog()->recordChatLog($uid, $fUid, $content)) {
            FF::throwException(Exceptions::RET_CHAT_SEND_FAIL, 'send chat fail');
        }

        Bll::chatLog()->incUnreadCnt($fUid, $uid);
        Bll::messageNotify()->receiveChatMsg($fUid, $uid, $content);
        return [];
    }

    public function fetchMessageList()
    {
        $uid = $this->getUid();
        $fUid = $this->getParam('fUid');
        return Bll::chatLog()->getMsgList($uid, $fUid);
    }

    public function readAll()
    {
        $uid = $this->getUid();
        $fUid = $this->getParam('fUid');
        Bll::chatLog()->ReadAll($uid, $fUid);
        return [];
    }
}