<?php
/**
 * FacebookSdk
 * @property \Facebook\Facebook $sdk
 */

namespace FF\Library\Sdk;

use Facebook\Facebook;

file_require(PATH_LIB . '/Vendor/Facebook/autoload.php');

class FacebookSdk
{
    private $sdk;
    private $config;
    private $picture = 'https://graph.facebook.com/%s/picture?width=200&height=200';

    public function __construct($config)
    {
        $this->config = $config;
        $this->sdk = new Facebook($this->config);
    }

    public function authLogin($openid, $token)
    {
        $oAuth2Client = $this->sdk->getOAuth2Client();
        $tokenMetadata = $oAuth2Client->debugToken($token);
        $tokenMetadata->validateAppId($this->config['app_id']);
        $tokenMetadata->validateUserId($openid);
        $tokenMetadata->validateExpiration();
    }

    public function getUser($token)
    {
        try {
            $response = $this->sdk->get('/me', $token);
            $user = $response->getGraphUser();
            return array(
                'name' => $user->getName(),
                'picture' => sprintf($this->picture, $user->getId()),
                'email' => $user->getEmail()
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getFriends($token)
    {
        try {
            $list = array();
            $response = $this->sdk->get('/me/friends', $token);
            $metaData = $response->getDecodedBody();
            if ($metaData && isset($metaData['data'])) {
                foreach ($metaData['data'] as $row) {
                    $list[] = $row['id'];
                }
            }
            return $list;
        } catch (\Exception $e) {
            return null;
        }
    }
}