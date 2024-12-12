<?php
/**
 * 用户业务逻辑
 */

namespace FF\Bll;

use FF\App\GameMain\Model\Main\UserModel;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Library\Utils\Utils;

class UserBll extends DBCacheBll
{
    protected $fields = array(
        'uid' => ['int', null],
        'name' => ['string', ''],
        'deviceId' => ['string', ''],
        'level' => ['int', 0],
        'clubId' => ['int', 0],
        'headId' => ['string', ''],
        'facebookId' => ['string', ''],
        'lastOnlineTime' => ['int', 0],
    );

    protected $updateFields = array(
        'name' => ['string', ''],
        'level' => ['int', ''],
        'headId' => ['string', ''],
        'facebookId' => ['string', ''],
        'lastOnlineTime' => ['int', 0],
    );

    public $onlyDQL = true;

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

    public function resetCacheData($uid)
    {
        $key = $this->getCacheKey($uid, null);
        $this->redis()->expire($key, -1);
        $this->getCacheData($uid);
    }
    public function updateUserInfo($uid, $data)
    {
        $update = [];
        foreach ($this->updateFields as $field => $defArr) {
            if (!isset($data[$field])) {
                continue;
            }
            $update[$field] = Utils::dataFormat($data[$field], $defArr[0]);
        }

        if (!$update) return true;

        return $this->updateCacheData($uid, $update);
    }

    /**
     * 检查用户是否在线
     */
    public function isOnline($uid)
    {
        $data = $this->getCacheData($uid, 'lastOnlineTime');
        if (!$data || $data['lastOnlineTime'] == 0) {
            return false;
        }

        if ((time() - $data['lastOnlineTime']) > 300) {
            return false;
        }

        return true;
    }
}