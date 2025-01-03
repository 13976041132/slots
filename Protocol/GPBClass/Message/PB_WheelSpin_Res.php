<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: PB_WheelSpin.proto

namespace GPBClass\Message;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>GPBClass.Message.PB_WheelSpin_Res</code>
 */
class PB_WheelSpin_Res extends \Google\Protobuf\Internal\Message
{
    /**
     *中奖位置
     *
     * Generated from protobuf field <code>int32 pos = 1;</code>
     */
    private $pos = 0;
    /**
     *奖励列表
     *
     * Generated from protobuf field <code>repeated .GPBClass.Message.PB_PrizeInfo prizes = 2;</code>
     */
    private $prizes;
    /**
     *下个转盘ID
     *
     * Generated from protobuf field <code>string nextWheelId = 3;</code>
     */
    private $nextWheelId = '';
    /**
     *扣费消耗
     *
     * Generated from protobuf field <code>int32 cost = 4;</code>
     */
    private $cost = 0;
    /**
     *feature结算信息
     *
     * Generated from protobuf field <code>.GPBClass.Message.PB_FeatureWinInfo winInfo = 5;</code>
     */
    private $winInfo = null;

    public function __construct() {
        \GPBMetadata\PBWheelSpin::initOnce();
        parent::__construct();
    }

    /**
     *中奖位置
     *
     * Generated from protobuf field <code>int32 pos = 1;</code>
     * @return int
     */
    public function getPos()
    {
        return $this->pos;
    }

    /**
     *中奖位置
     *
     * Generated from protobuf field <code>int32 pos = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setPos($var)
    {
        GPBUtil::checkInt32($var);
        $this->pos = $var;

        return $this;
    }

    /**
     *奖励列表
     *
     * Generated from protobuf field <code>repeated .GPBClass.Message.PB_PrizeInfo prizes = 2;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getPrizes()
    {
        return $this->prizes;
    }

    /**
     *奖励列表
     *
     * Generated from protobuf field <code>repeated .GPBClass.Message.PB_PrizeInfo prizes = 2;</code>
     * @param \GPBClass\Message\PB_PrizeInfo[]|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setPrizes($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \GPBClass\Message\PB_PrizeInfo::class);
        $this->prizes = $arr;

        return $this;
    }

    /**
     *下个转盘ID
     *
     * Generated from protobuf field <code>string nextWheelId = 3;</code>
     * @return string
     */
    public function getNextWheelId()
    {
        return $this->nextWheelId;
    }

    /**
     *下个转盘ID
     *
     * Generated from protobuf field <code>string nextWheelId = 3;</code>
     * @param string $var
     * @return $this
     */
    public function setNextWheelId($var)
    {
        GPBUtil::checkString($var, True);
        $this->nextWheelId = $var;

        return $this;
    }

    /**
     *扣费消耗
     *
     * Generated from protobuf field <code>int32 cost = 4;</code>
     * @return int
     */
    public function getCost()
    {
        return $this->cost;
    }

    /**
     *扣费消耗
     *
     * Generated from protobuf field <code>int32 cost = 4;</code>
     * @param int $var
     * @return $this
     */
    public function setCost($var)
    {
        GPBUtil::checkInt32($var);
        $this->cost = $var;

        return $this;
    }

    /**
     *feature结算信息
     *
     * Generated from protobuf field <code>.GPBClass.Message.PB_FeatureWinInfo winInfo = 5;</code>
     * @return \GPBClass\Message\PB_FeatureWinInfo
     */
    public function getWinInfo()
    {
        return $this->winInfo;
    }

    /**
     *feature结算信息
     *
     * Generated from protobuf field <code>.GPBClass.Message.PB_FeatureWinInfo winInfo = 5;</code>
     * @param \GPBClass\Message\PB_FeatureWinInfo $var
     * @return $this
     */
    public function setWinInfo($var)
    {
        GPBUtil::checkMessage($var, \GPBClass\Message\PB_FeatureWinInfo::class);
        $this->winInfo = $var;

        return $this;
    }

}

