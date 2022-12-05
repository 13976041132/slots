<?php
/**
 * class IpIpSdk
 */

namespace FF\Library\Sdk;

use ipip\db\City;

file_require(PATH_LIB . '/Vendor/IpIp/vendor/autoload.php');

class IpIpSdk
{
    private $client;

    public function __construct()
    {
        $this->client = new City(PATH_LIB . '/Vendor/IpIp/src/ipip/db/ipipfree.ipdb');
    }

    public function getCountry($ip)
    {
        $cityInfo = $this->client->findInfo($ip, 'CN');

        if (!$cityInfo) return '';

        return $cityInfo->country_name;
    }
}