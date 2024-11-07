<?php
/**
 * 社交好友业务模块
 */

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class FriendsModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 'friends');
    }

    /**
     * 获取好友列表
     */
    public function getAll($uid)
    {
        $where = array(
            'uid' => $uid
        );

        return $this->fetchAll($where);
    }

    /**
     * 添加好友关系，添加两条记录
     * uid => fUid
     * fUid => uid
     */
    public function addFriend($uid, $fUid)
    {
        $data = array();
        $data[] = array('uid' => $uid, 'fuid' => $fUid, 'createTime' => now());
        $data[] = array('uid' => $fUid, 'fuid' => $uid, 'createTime' => now());

        return $this->insertMulti($data);
    }

    /**
     * 删除好友关系，删除两条记录
     * uid => fUid
     * fUid => uid
     */
    public function delFriend($uid, $fUid)
    {
        $where = array(
            'uid' => array('in', [$uid, $fUid]),
            'fuid' => array('in', [$uid, $fUid])
        );

        return $this->delete($where, 2);
    }
    public function getUnreadCnt($uid)
    {
        $info = $this->fetchOne(['uid' => $uid], 'sum(unReadCnt) total');
        return $info['total'] ?? 0;
    }
}