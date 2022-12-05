<?php
/**
 * 在线信息数据模块
 */

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class OnlineModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 't_online', 'uid');
    }

    public function addOne($uid)
    {
        $data = array(
            'uid' => $uid,
            'isOnline' => 1,
            'onlineTime' => now(),
            'activeTime' => now(),
        );

        return $this->insert($data);
    }

    public function autoOffline()
    {
        $updates = array(
            'isOnline' => 0,
            'isPlaying' => 0,
            'offlineTime' => now()
        );

        $where = array(
            'isOnline' => 1,
            'activeTime' => array('<', date('Y-m-d H:i:s', time() - 180))
        );

        return $this->update($updates, $where, 0);
    }

    public function getOnlineTime($uid)
    {
        $result = $this->fetchOne(['uid' => $uid], 'totalTime');
        return $result ? $result['totalTime'] : 0;
    }

    public function getOnlineCount()
    {
        $where = array(
            'isOnline' => 1
        );

        $result = $this->fetchOne($where, 'COUNT(1) AS `count`');

        return $result['count'];
    }

    public function getPlayingCount()
    {
        $where = array(
            'isPlaying' => 1
        );

        $result = $this->fetchOne($where, 'COUNT(1) AS `count`');

        return $result['count'];
    }

    public function getOfflineUsers()
    {
        $where = array(
            'isOnline' => 0,
            'offlineTime' => array('<', date('Y-m-d H:i:s', time() - 180))
        );

        $result = $this->fetchAll($where, 'uid');

        return array_column($result, 'uid');
    }

    public function clearOffline()
    {
        $where = array(
            'isOnline' => 0,
            'offlineTime' => array('<', date('Y-m-d H:i:s', time() - 180))
        );

        return $this->delete($where, 0);
    }
}