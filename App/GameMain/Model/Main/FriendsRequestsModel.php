<?php
/**
 * 社交好友申请业务模块
 */

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class FriendsRequestsModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'friends_requests', 'uuid');
    }

    /**
     * 获取好友申请列表
     */
    public function getAll($uid)
    {
        $where = array(
            'fuid' => $uid,
            'status' => 0
        );

        $orderBy = array('requestTime' => 'desc');
        return $this->fetchAll($where, null, $orderBy);
    }

    /**
     * 添加好友申请关系
     */
    public function addFriendRequest($uuid, $uid, $fUid)
    {
        $time = now();
        $update = "`requestTime` = '{$time}', uid = {$uid}, fuid = {$fUid}, `status` = 0";
        $sql = "INSERT INTO {$this->table()} VALUES ({$uuid}, {$uid}, '{$fUid}', 0, '{$time}', '{$time}') ON DUPLICATE KEY UPDATE {$update}";

        return $this->db()->query($sql);
    }

    /**
     * 接受好友申请关系
     */
    public function acceptFriendRequest($uid, $fUid)
    {
        $data = array('status' => 1);
        $where = array(
            'uid' => array('in', [$uid, $fUid]),
            'fuid' => array('in', [$uid, $fUid])
        );


        return $this->update($data, $where, 2);
    }

    /**
     * 拒绝好友申请关系
     */
    public function refuseFriendRequest($uid, $fUid)
    {
        $data = array('status' => 2);
        $where = array(
            'uid' => $fUid,
            'fuid' => $uid,
        );

        return $this->update($data, $where);
    }
}