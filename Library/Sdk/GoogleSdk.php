<?php
/**
 * GoogleSdk
 */

namespace FF\Library\Sdk;

use FF\Framework\Utils\Log;

file_require(PATH_LIB . '/Vendor/Google/vendor/autoload.php');

class GoogleSdk
{
    private $config;

    private $client;

    public function __construct($config)
    {
        $this->config = $config;
        $client = new \Google_Client();
        $client->setScopes(array('https://www.googleapis.com/auth/androidpublisher'));
        $client->setAuthConfig($this->config);
        $this->client = $client;
    }

    public function queryOrder($productId, $token)
    {
        for ($i = 0; $i < 3; ++$i) {
            try {
                $packageName = $this->config['package_name'];
                $service = new \Google_Service_AndroidPublisher($this->client);
                $resp = $service->purchases_products->get($packageName, $productId, $token);
                return $resp;
            } catch (\Exception $e) {
                $log = array('productId' => $productId, 'token' => $token, 'error' => $e->getMessage());
                Log::error($log, 'google_query_order.log');
            }
        }

        return null;
    }
}