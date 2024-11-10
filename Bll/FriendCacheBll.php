<?php

namespace FF\Bll;

use FF\App\GameMain\Model\Main\FriendsModel;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
class FriendCacheBll extends DBCacheBll
{
    protected $uniqueKey = 'uuid';

    protected $fields = array(
        'uid' => ['int', null],
        'fuid' => ['int', 0],
        'unReadCnt' => ['int', 0],
        'givingGiftTimes' => ['int', 0],
        'receiveGiftTimes' => ['int', 0],
    );

    /**
     * @return FriendsModel
     */
    function model($uid)
    {
        return Model::friends();
    }

    function getCacheKey($uid, $wheres)
    {
        return Keys::friendInfo($uid);
    }

    /**
     * 获取用户信息
     */
    public function getInfo($uid, $fuid, $fields = null)
    {
        $uuid = $this->makeUUID($uid, $fuid);
        return $this->getCacheData($uuid, $fields);
    }

    /**
     * 获取批量用户信息
     */
    public function getFriendList($uid, array $fuids, $fields = null)
    {
        $uuids = [];
        foreach ($fuids as $fuid) {
            $uuids[] = $this->makeUUID($uid, $fuid);
        }
        return $this->getCacheList($uuids, $fields);
    }

    public function makeUUID($uid, $fuid)
    {
        return $uid . '-' . $fuid;
    }

    public function updateData($uid, $fuid, $data)
    {
        $uuid = $this->makeUUID($uid, $fuid);
        $this->updateCacheData($uuid, $data, null, true);
    }
    public function batchUpdateFieldByInc($uid, $fuids, $field)
    {
        $valCnt = array_count_values($fuids);
        foreach ($valCnt as $fuid => $cnt) {
            $uuid = $this->makeUUID($uid, $fuid);
            $result = $this->updateFieldByInc($uuid, $field, $cnt,'', $newValue);
            if ($result) {
                $this->updateCacheData($uuid, [$field => $newValue], null, true);
            }
        }
    }

    public function delFriend($uid, $fuid)
    {
        $result = Model::friends()->delFriend($uid, $fuid);
        $uuids = [$this->makeUUID($uid, $fuid), $this->makeUUID($fuid, $uid)];
        foreach ($uuids as $uuid) {
            $key = $this->getCacheKey($uuid, []);
            $this->redis()->del($key);
        }
        return $result;
    }

    public function addFriend($uid, $fuid)
    {
        $data = [];
        $data[] = array('uuid' => $this->makeUUID($uid, $fuid),'uid' => $uid, 'fuid' => $fuid, 'createTime' => now());
        $data[] = array('uuid' => $this->makeUUID($fuid, $uid), 'uid' => $fuid, 'fuid' => $uid, 'createTime' => now());
        return Model::friends()->insertMulti($data);
    }

}