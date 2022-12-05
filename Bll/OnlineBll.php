<?php
/**
 * 玩家在线/离线业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Model;

class OnlineBll
{
    public function setOnline($uid, $loginType)
    {
        $exist = Model::online()->getOneById($uid, 'uid');
        if ($exist) {
            $updates = array(
                'isOnline' => 1,
                'isPlaying' => 0,
                'onlineTime' => now(),
                'activeTime' => now(),
                'offlineTime' => null,
            );
            if ($loginType != 0) {
                $updates['totalTime'] = 0;
            }
            Model::online()->updateById($uid, $updates);
        } else {
            Model::online()->addOne($uid);
        }
    }

    public function setOffline($uid)
    {
        Model::online()->updateById($uid, array(
            'isOnline' => 0,
            'isPlaying' => 0,
            'offlineTime' => now()
        ));
    }

    public function updateOnline($uid, $isPlaying)
    {
        Model::online()->updateById($uid, array(
            'isOnline' => 1,
            'isPlaying' => (int)$isPlaying,
            'activeTime' => now(),
            'totalTime' => array('+=', 30)
        ));
    }

    public function autoOffline()
    {
        Model::online()->autoOffline();
    }

    public function getOnlineCount()
    {
        return Model::online()->getOnlineCount();
    }

    public function getPlayingCount()
    {
        return Model::online()->getPlayingCount();
    }

    public function getOfflineUsers()
    {
        return Model::online()->getOfflineUsers();
    }

    public function clearOffline()
    {
        Model::online()->clearOffline();
    }
}