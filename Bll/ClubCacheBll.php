<?php

namespace FF\Bll;

use FF\App\GameMain\Model\Main\ClubsModel;
use FF\Factory\Keys;
use FF\Factory\Model;

class ClubCacheBll extends DBCacheBll
{
    protected $uniqueKey = 'clubId';

    protected $fields = array(
        'clubId' => ['int', null],
        'creator' => ['int', 0],
        'type' => ['int', 0],
        'level' => ['int', 0],
        'clubName' => ['string', ''],
        'headId' => ['int', 0],
        'memberCnt' => ['int', 0],
        'coins' => ['double', 0],
        'dan' => ['int', 0],
        'createTime' => ['string', ''],
        'vipLimit' => ['int', 0],
        'donateTimes' => ['int', 0],
        'topDonor' => ['int', 0],
    );

    /**
     * @return ClubsModel
     */
    function model($uuid)
    {
        return Model::clubs();
    }

    function getCacheKey($clubId, $wheres)
    {
        return Keys::clubInfo($clubId);
    }

    /**
     * 获取用户信息
     */
    public function getInfo($clubId, $fields = null)
    {
        return $this->getCacheData($clubId, $fields);
    }

    /**
     * 获取批量用户信息
     */
    public function getClubList($clubIds, $fields = null)
    {
        return $this->getCacheList($clubIds, $fields);
    }

    public function updateData($clubId, $data)
    {
        return $this->updateCacheData($clubId, $data, null, true);
    }

    public function updateClubByInc($clubId, $field, $value, $newValue = 0)
    {
        $result = $this->updateFieldByInc($clubId, $field, $value, '', $newValue);
        if ($result) {
            $this->updateCacheData($clubId, [$field => $newValue], null, true);
        }
        return $result;
    }
}