<?php
/**
 * 物品相关逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Utils\Config;
use GPBClass\Enum\ITEM_TYPE;

class ItemBll
{
    /**
     * 物品集合转化为列表
     */
    public function toList($items)
    {
        $list = array();

        foreach ($items as $itemId => $count) {
            if (!$count) continue;
            $itemConfig = Config::get('common/items', $itemId);
            if (!$itemConfig) continue;
            $itemType = $itemConfig['type'];
            if ($itemId == ITEM_FEATURE || $itemId == ITEM_WHEEL) {
                $count = 1;
            } elseif ($itemId == ITEM_JACKPOT) {
                $jackpotPrize = $count;
                $count = $jackpotPrize['coins'];
            } elseif ($itemId == ITEM_FREE_SPIN) {
                $freeSpin = $count;
                $count = $freeSpin['times'];
            } elseif (is_array($count)) {
                $options = $count;
                $count = isset($options['count']) ? $options['count'] : 1;
            } else {
                $count = (float)$count;
            }
            $list[] = array(
                'itemId' => $itemId, 'type' => $itemType, 'count' => $count
            );
        }

        return $list;
    }

    /**
     * 获取玩家的道具列表
     */
    public function getItemList($uid, $itemIds = null)
    {
        $list = array();

        $items = Model::item()->getCounts($uid, $itemIds);

        foreach ($items as $itemId => $count) {
            $itemConfig = Config::get('common/items', $itemId);
            if (!$itemConfig) continue;
            $list[] = array(
                'itemId' => $itemId, 'type' => $itemConfig['type'], 'count' => $count
            );
        }

        return $list;
    }

    public function addItems($uid, $items, $reason = '', $options = null)
    {
        $list = array();
        if (!$items) return $list;

        $reason = str_replace(' ', '', $reason);

        foreach ($items as $itemId => $count) {
            if (!$count) continue;
            $itemConfig = Config::get('common/items', $itemId);
            if (!$itemConfig) continue;
            if (is_array($count)) {
                $_options = $count;
                $_options = $options ? array_merge($options, $_options) : $_options;
                $count = isset($_options['count']) ? (float)$_options['count'] : 1;
                unset($_options['count']);
            } else if (is_numeric($count)) {
                $count = (float)$count;
                $_options = $options;
            } else {
                $_options = $options;
                $count = 1;
            }
            $itemType = $itemConfig['type'];
            $balance = $this->addItem($uid, $itemId, $itemType, $count, $reason, $_options);
            if ($balance === false) continue;
            $list[] = array(
                'itemId' => $itemId, 'type' => $itemType, 'count' => $count, 'balance' => $balance
            );
        }

        return $list;
    }

    /**
     * 给用户发放物品道具
     */
    public function addItem($uid, $itemId, $itemType, $count, $reason = '', $options = null)
    {
        if ($count <= 0) return false;

        $newCount = $count;

        switch ($itemType) {
            case ITEM_TYPE::ITEM_TYPE_COINS:
                Bll::user()->addCoins($uid, $count, $reason, $newCount);
                break;
            case ITEM_TYPE::ITEM_TYPE_FREE_SPIN:
                $machineId = Bll::game()->getPlayingMachineId($uid);
                if (!$machineId || empty($options['featureId'])) return false;
                $machineObj = Bll::machine()->getMachineInstance($uid, $machineId);
                $machineObj->setFreeGameByAward($options['featureId'], $count, $options);
                break;
            default:
                //Model::item()->addItem($uid, $itemId, $count);
                //$newCount = Model::item()->getCount($uid, $itemId);
                break;
        }

        return $newCount;
    }

    /**
     * 扣除用户道具
     */
    public function decItem($uid, $itemId, $count = 1)
    {
        return Model::item()->decItem($uid, $itemId, $count);
    }

    /**
     * 扣除用户道具-批量
     */
    public function decItems($uid, $items)
    {
        if (!is_array($items)) return false;

        return Model::item()->decItems($uid, $items);
    }
}