<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: PB_CheckAppVersion.proto

namespace GPBClass\Message;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>GPBClass.Message.PB_CheckAppVersion_Req</code>
 */
class PB_CheckAppVersion_Req extends \Google\Protobuf\Internal\Message
{
    /**
     *大版本号
     *
     * Generated from protobuf field <code>string bigVersion = 1;</code>
     */
    private $bigVersion = '';
    /**
     *小版本号
     *
     * Generated from protobuf field <code>int32 smallVersion = 2;</code>
     */
    private $smallVersion = 0;

    public function __construct() {
        \GPBMetadata\PBCheckAppVersion::initOnce();
        parent::__construct();
    }

    /**
     *大版本号
     *
     * Generated from protobuf field <code>string bigVersion = 1;</code>
     * @return string
     */
    public function getBigVersion()
    {
        return $this->bigVersion;
    }

    /**
     *大版本号
     *
     * Generated from protobuf field <code>string bigVersion = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setBigVersion($var)
    {
        GPBUtil::checkString($var, True);
        $this->bigVersion = $var;

        return $this;
    }

    /**
     *小版本号
     *
     * Generated from protobuf field <code>int32 smallVersion = 2;</code>
     * @return int
     */
    public function getSmallVersion()
    {
        return $this->smallVersion;
    }

    /**
     *小版本号
     *
     * Generated from protobuf field <code>int32 smallVersion = 2;</code>
     * @param int $var
     * @return $this
     */
    public function setSmallVersion($var)
    {
        GPBUtil::checkInt32($var);
        $this->smallVersion = $var;

        return $this;
    }

}

