<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: PB_SetAvatar.proto

namespace GPBClass\Message;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>GPBClass.Message.PB_SetAvatar_Req</code>
 */
class PB_SetAvatar_Req extends \Google\Protobuf\Internal\Message
{
    /**
     *用户头像
     *
     * Generated from protobuf field <code>string avatar = 1;</code>
     */
    private $avatar = '';

    public function __construct() {
        \GPBMetadata\PBSetAvatar::initOnce();
        parent::__construct();
    }

    /**
     *用户头像
     *
     * Generated from protobuf field <code>string avatar = 1;</code>
     * @return string
     */
    public function getAvatar()
    {
        return $this->avatar;
    }

    /**
     *用户头像
     *
     * Generated from protobuf field <code>string avatar = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setAvatar($var)
    {
        GPBUtil::checkString($var, True);
        $this->avatar = $var;

        return $this;
    }

}

