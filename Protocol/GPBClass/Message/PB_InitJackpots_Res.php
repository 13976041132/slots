<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: PB_InitJackpots.proto

namespace GPBClass\Message;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>GPBClass.Message.PB_InitJackpots_Res</code>
 */
class PB_InitJackpots_Res extends \Google\Protobuf\Internal\Message
{
    /**
     *jackpot列表
     *
     * Generated from protobuf field <code>repeated .GPBClass.Message.PB_JackpotInfo jackpots = 1;</code>
     */
    private $jackpots;

    public function __construct() {
        \GPBMetadata\PBInitJackpots::initOnce();
        parent::__construct();
    }

    /**
     *jackpot列表
     *
     * Generated from protobuf field <code>repeated .GPBClass.Message.PB_JackpotInfo jackpots = 1;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getJackpots()
    {
        return $this->jackpots;
    }

    /**
     *jackpot列表
     *
     * Generated from protobuf field <code>repeated .GPBClass.Message.PB_JackpotInfo jackpots = 1;</code>
     * @param \GPBClass\Message\PB_JackpotInfo[]|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setJackpots($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \GPBClass\Message\PB_JackpotInfo::class);
        $this->jackpots = $arr;

        return $this;
    }

}

