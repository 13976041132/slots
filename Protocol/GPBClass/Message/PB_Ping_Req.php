<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: PB_Ping.proto

namespace GPBClass\Message;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>GPBClass.Message.PB_Ping_Req</code>
 */
class PB_Ping_Req extends \Google\Protobuf\Internal\Message
{
    /**
     *是否在玩[1-是|0-否]
     *
     * Generated from protobuf field <code>int32 isPlaying = 1;</code>
     */
    private $isPlaying = 0;

    public function __construct() {
        \GPBMetadata\PBPing::initOnce();
        parent::__construct();
    }

    /**
     *是否在玩[1-是|0-否]
     *
     * Generated from protobuf field <code>int32 isPlaying = 1;</code>
     * @return int
     */
    public function getIsPlaying()
    {
        return $this->isPlaying;
    }

    /**
     *是否在玩[1-是|0-否]
     *
     * Generated from protobuf field <code>int32 isPlaying = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setIsPlaying($var)
    {
        GPBUtil::checkInt32($var);
        $this->isPlaying = $var;

        return $this;
    }

}

