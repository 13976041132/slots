<?php

namespace FF\Bll;

use FF\App\GameMain\Model\Main\UserRequestLastModel;
use FF\Factory\Bll;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Framework\Utils\Input;

class UserRequestLastBll extends DBCacheBll
{
    protected $uniqueKey = 'uid';
    public $onlyDQL = true;
    private $info = [];
    private $secretFresh = false;

    protected $fields = array(
        'requestId' => ['int', 0],
        'secretKey' => ['string', ''],
    );

    /**
     * @return UserRequestLastModel
     */
    function model($uid)
    {
        return Model::userRequestLast();
    }

    function getCacheKey($uid, $wheres)
    {
        return Keys::userRequestLastInfo($uid);
    }

    public function __construct()
    {
        $this->info = $this->getCacheData(Bll::session()->get('uid'));;
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
        $uid = Bll::session()->get('uid');
        $this->updateCacheData($uid, $upData);
    }

    public function setFreshSecretKey($fresh)
    {
        $this->secretFresh = $fresh;
    }

    public function getSecretStatus()
    {
        return $this->secretFresh;
    }
}