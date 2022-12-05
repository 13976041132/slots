<?php
/**
 * AmazonS3
 */

namespace FF\Library\Sdk;

use Aws\CloudFront\CloudFrontClient;
use FF\Framework\Utils\Log;

file_require(PATH_LIB . '/Vendor/Aws/aws-autoloader.php', true);

class AmazonCdn
{
    private $cloudfront;

    private $version;
    private $credentials;
    private $distributionId;

    public function __construct($options = array())
    {
        $this->version = $options['version'];
        $this->credentials = $options['credentials'];
        $this->distributionId = $options['distributionId'];
    }

    public function cloudfront()
    {
        if ($this->cloudfront) {
            return $this->cloudfront;
        }

        $cloudfront = new CloudFrontClient(array(
            'version' => $this->version,
            'credentials' => $this->credentials,
            'region' => ''
        ));

        $this->cloudfront = $cloudfront;

        return $cloudfront;
    }

    public function refreshItems($items)
    {
        $time = time();
        $result = $this->cloudfront()->createInvalidation(array(
            'DistributionId' => $this->distributionId,
            'InvalidationBatch' => array(
                'CallerReference' => time(),
                'Paths' => array(
                    'Items' => $items,
                    'Quantity' => count($items)
                )
            )
        ));
        $cost = time() - $time;
        Log::info(array(
            'invalidation' => $result['Invalidation'],
            'cost' => $cost
        ), 'cdn.log');
    }
}