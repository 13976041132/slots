<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: PB_CheckAppVersion.proto

namespace GPBClass\Message;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>GPBClass.Message.PB_CheckAppVersion_Res</code>
 */
class PB_CheckAppVersion_Res extends \Google\Protobuf\Internal\Message
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
    /**
     *更新包地址
     *
     * Generated from protobuf field <code>string packageUrl = 3;</code>
     */
    private $packageUrl = '';
    /**
     *是否有更新
     *
     * Generated from protobuf field <code>bool hasUpdate = 4;</code>
     */
    private $hasUpdate = false;
    /**
     *是否强制更新
     *
     * Generated from protobuf field <code>bool forceUpdate = 5;</code>
     */
    private $forceUpdate = false;
    /**
     *更新包MD5值
     *
     * Generated from protobuf field <code>string md5 = 6;</code>
     */
    private $md5 = '';

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

    /**
     *更新包地址
     *
     * Generated from protobuf field <code>string packageUrl = 3;</code>
     * @return string
     */
    public function getPackageUrl()
    {
        return $this->packageUrl;
    }

    /**
     *更新包地址
     *
     * Generated from protobuf field <code>string packageUrl = 3;</code>
     * @param string $var
     * @return $this
     */
    public function setPackageUrl($var)
    {
        GPBUtil::checkString($var, True);
        $this->packageUrl = $var;

        return $this;
    }

    /**
     *是否有更新
     *
     * Generated from protobuf field <code>bool hasUpdate = 4;</code>
     * @return bool
     */
    public function getHasUpdate()
    {
        return $this->hasUpdate;
    }

    /**
     *是否有更新
     *
     * Generated from protobuf field <code>bool hasUpdate = 4;</code>
     * @param bool $var
     * @return $this
     */
    public function setHasUpdate($var)
    {
        GPBUtil::checkBool($var);
        $this->hasUpdate = $var;

        return $this;
    }

    /**
     *是否强制更新
     *
     * Generated from protobuf field <code>bool forceUpdate = 5;</code>
     * @return bool
     */
    public function getForceUpdate()
    {
        return $this->forceUpdate;
    }

    /**
     *是否强制更新
     *
     * Generated from protobuf field <code>bool forceUpdate = 5;</code>
     * @param bool $var
     * @return $this
     */
    public function setForceUpdate($var)
    {
        GPBUtil::checkBool($var);
        $this->forceUpdate = $var;

        return $this;
    }

    /**
     *更新包MD5值
     *
     * Generated from protobuf field <code>string md5 = 6;</code>
     * @return string
     */
    public function getMd5()
    {
        return $this->md5;
    }

    /**
     *更新包MD5值
     *
     * Generated from protobuf field <code>string md5 = 6;</code>
     * @param string $var
     * @return $this
     */
    public function setMd5($var)
    {
        GPBUtil::checkString($var, True);
        $this->md5 = $var;

        return $this;
    }

}

