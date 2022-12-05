<?php

namespace FF\Bll;

use FF\Factory\Dao;

class RechargeBll
{
    public function getCacheKey($uid)
    {
        return 'store:' . $uid;
    }

    public function getFields()
    {
        return array(
            'totalRecharge',
            'payMaxPrice',
            'upRecharge',
            'upRechargeTime',
            'rechargePlayNum',
        );
    }

    /**
     * 获取用户付费统计信息
     */
    public function getRechargeInfo($uid)
    {
        $info = [];
        $fields = $this->getFields();
        $key = $this->getCacheKey($uid);
        $result = Dao::redis()->hMGet($key, $fields);

        foreach ($fields as $field) {
            $info[$field] = $result[$field] ?: 0;
        }

        if($info['totalRecharge']) {
            $info['rechargePlayNum'] = $this->getRechargePlayNum($uid);
        }

        return $info;
    }

    protected function getRechargePlayNum($uid)
    {
        $rechargePlayNum = (int)Dao::redis()->hGet('game_store:' . $uid, 'rechargePlayNum');

        return $rechargePlayNum ?: 0;
    }
}