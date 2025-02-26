<?php

namespace FF\App\GameMain\Controller;
use FF\Constants\MessageIds;
use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;

class BllMessageController extends BaseController
{
    //获取业务信息
    public function fetchMessageList()
    {
        $uid = $this->getUid();
        $userInfo = Bll::user()->getUserInfo($uid);
        Bll::user()->updateUserInfo($uid, ['lastOnlineTime' => time()]);
        //通过队列获取玩家相关业务推送信息
        $key = Keys::bllMessageQueue($uid);
        $list = [];
        $cnt = 200;
        while ($cnt > 0) {
            $row = Dao::redis()->lPop($key);
            if (!$row) {
                break;
            }
            $list[] = json_decode($row, true);
            --$cnt;
        }
        return $list;
    }

    //消息统计列表
    public function fetchMsgStatInfo()
    {
        $uid = $this->getUid();
        $msgStatData = Bll::user()->fetchMsgStatInfo($uid);
        return array_merge(
            $msgStatData,
            [
                'lastRequestId' => Model::userRequestLast()->getRequestId($uid)
            ]
        );
    }
}