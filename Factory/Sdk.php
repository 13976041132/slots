<?php
/**
 * Sdk对象工厂
 */

namespace FF\Factory;

use FF\Framework\Mode\Factory;
use FF\Framework\Utils\Config;
use FF\Library\Sdk\AmazonCdn;
use FF\Library\Sdk\AmazonS3;
use FF\Library\Sdk\AppleSdk;
use FF\Library\Sdk\FacebookSdk;
use FF\Library\Sdk\GoogleSdk;
use FF\Library\Sdk\IpIpSdk;

class Sdk extends Factory
{
    /**
     * @return AmazonS3
     */
    public static function amazonS3()
    {
        $args = array(Config::get('sdks', 'amazon-s3'));

        return self::getInstance('\FF\Library\Sdk\AmazonS3', null, $args);
    }

    /**
     * @return IpIpSdk
     */
    public static function ipip()
    {
        return self::getInstance('\FF\Library\Sdk\IpIpSdk');
    }

    /**
     * @return AmazonCdn
     */
    public static function amazonCdn()
    {
        $args = array(Config::get('sdks', 'amazon-cdn'));

        return self::getInstance('\FF\Library\Sdk\AmazonCdn', null, $args);
    }

    /**
     * @return FacebookSdk
     */
    public static function facebook()
    {
        $args = array(Config::get('sdks', 'facebook'));

        return self::getInstance('\FF\Library\Sdk\FacebookSdk', null, $args);
    }

    /**
     * @return GoogleSdk
     */
    public static function google()
    {
        $args = array(Config::get('sdks', 'google'));

        return self::getInstance('\FF\Library\Sdk\GoogleSdk', null, $args);
    }

    /**
     * @return AppleSdk
     */
    public static function apple()
    {
        $args = [array('method' => 'post')];

        return self::getInstance('\FF\Library\Sdk\AppleSdk', null, $args);
    }

}