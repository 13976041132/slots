<?php
/**
 * 用户业务逻辑
 */

namespace FF\Bll;

use FF\App\GameMain\Model\Main\UserModel;
use FF\Factory\Keys;
use FF\Factory\Model;

class UserBll extends DBCacheBll
{

    protected $fields = array(
        'uid' => ['int', null],
        'name' => ['string', ''],
        'deviceId' => ['string', ''],
    );

    /**
     * @return UserModel
     */
    function model($uid)
    {
        return Model::user();
    }

    function getCacheKey($uid, $wheres)
    {
        return Keys::userInfo($uid);
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo($uid, $fields = null)
    {
        if (!is_numeric($uid) || $uid <= 0) {
            return [];
        }
        return $this->getCacheData($uid, $fields);
    }

    /**
     * 根据用户ID批量获取用户信息
     */
    public function getMulti($uids, $fields = null)
    {
        $list = array();
        foreach ($uids as $uid) {
            $info = $this->getUserInfo($uid, $fields);
            if ($info) $list[$uid] = $info;
        }
        return $list;
    }
    /**
     * 获取批量用户信息
     */
    public function getUserInfoList(array $uids, $fields = null)
    {
        return $this->getCacheList($uids, $fields);
    }
}