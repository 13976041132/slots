<?php
/**
 * 道具模块
 */

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class ItemModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 't_item');
    }

    public function getCount($uid, $itemId)
    {
        $where = array(
            'uid' => $uid, 'itemId' => $itemId
        );

        $result = $this->fetchOne($where, 'count');

        return $result ? $result['count'] : 0;
    }

    public function getCounts($uid, $itemIds = null)
    {
        $where = array(
            'uid' => $uid, 'count' => array('>', 0)
        );

        if ($itemIds) {
            $where['itemId'] = array('in', $itemIds);
        }

        $result = $this->fetchAll($where, 'itemId,count');

        return array_column($result, 'count', 'itemId');
    }

    public function addItem($uid, $itemId, $count = 1, $reset = false)
    {
        if ($count <= 0) return 0;

        $update = $reset ? "`count` = {$count}" : "`count` = `count` + {$count}";
        $sql = "INSERT INTO {$this->table()} VALUES ({$uid}, '{$itemId}', {$count}) ON DUPLICATE KEY UPDATE {$update}";

        return $this->db()->query($sql);
    }

    public function addItems($uid, $items)
    {
        $result = 0;

        foreach ($items as $itemId => $count) {
            $itemResult = $this->addItem($uid, $itemId, $count);
            $result += $itemResult['itemInfo']['balance'];
        }

        return $result;
    }

    public function decItem($uid, $itemId, $count)
    {
        if ($count <= 0) return 0;

        $where = array(
            'uid' => $uid,
            'itemId' => $itemId,
            'count' => array('>=', $count)
        );

        $updates = array(
            'count' => array('-=', $count)
        );

        return $this->update($updates, $where);
    }

    public function decItems($uid, $items)
    {
        $result = 0;

        foreach ($items as $itemId => $count) {
            $result += $this->decItem($uid, $itemId, $count);
        }

        return $result;
    }
}