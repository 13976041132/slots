<?php

namespace FF\Bll;

use FF\Factory\Keys;
use FF\Framework\Utils\Input;

class UserRequestLastBll extends RedisCacheBll
{
    protected $uniqueKey = 'uid';
    private $info = [];
    private $secretFresh = false;

    protected $fields = array(
        'uid' => ['int', 0],
        'requestId' => ['int', 0],
        'secretKey' => ['string', ''],
    );

    function getCacheKey($uid)
    {
        return Keys::userRequestLastInfo($uid);
    }

    public function __construct($uid)
    {
        $this->info = $this->getCacheData($uid);
    }

    //info
    public function get($key)
    {
        return $this->info[$key] ?? '';
    }

    public function touchSecretKey($secretFresh = false, $save = false)
    {
        if (empty($this->info['secretKey']) || $secretFresh) {
            $this->info['secretKey'] = md5(createNonceStr(32));
        }

        if ($save) $this->save();
        return $this->info['secretKey'];
    }

    public function getRequestId()
    {
        return $this->info['requestId'] ?? 0;
    }

    public function save()
    {
        $upData = [
            'requestId' => (string)Input::request('q', $this->getRequestId()),
            'secretKey' => $this->touchSecretKey($this->secretFresh),
        ];
        $this->updateCacheData($this->get('uid'), $upData);
    }

    public function setFreshSecretKey($fresh)
    {
        $this->secretFresh = $fresh;
    }

    public function getSecretStatus()
    {
        return $this->secretFresh;
    }
    public function resetData()
    {
        $this->clean($this->info['uid']);
        $this->info['secretKey'] = '';
        $this->info['requestId'] = 0;
    }
}