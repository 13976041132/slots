<?php
/**
 * 用户账号业务模块
 */

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class AccountModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 't_account', 'uid');
    }

    public function addOne($openid, $platform, $version, $deviceId, $deviceToken, $appsflyerId)
    {
        $data = array(
            'openid' => $openid,
            'platform' => $platform,
            'regVersion' => $version,
            'lastLoginVersion' => $version,
            'deviceId' => $deviceId,
            'deviceToken' => $deviceToken,
            'appsflyerId' => $appsflyerId,
            'regTime' => now(),
            'lastLoginTime' => now(),
            'continued' => 1,
            'status' => 1,
        );
        return $this->insert($data);
    }

    public function getOneByOpenid($openid, $platform)
    {
        $where = array('openid' => $openid, 'platform' => $platform);

        return $this->fetchOne($where);
    }

    public function getOneByBindOpenid($openid)
    {
        $where = array('bindOpenid' => $openid);

        return $this->fetchOne($where);
    }

    public function setBindOpenid($uid, $openid)
    {
        $updates = array('bindOpenid' => $openid);

        return $this->updateById($uid, $updates);
    }

    public function setBindUid($uid, $bindUid)
    {
        $updates = array('bindUid' => $bindUid);

        return $this->updateById($uid, $updates);
    }

    public function updateLogin($uid, $deviceId, $loginDays, $continued, $version)
    {
        $updates = array(
            'deviceId' => $deviceId,
            'lastLoginVersion' => $version,
            'lastLoginTime' => now(),
            'loginDays' => $loginDays,
            'continued' => $continued
        );

        return $this->updateById($uid, $updates);
    }

    public function getMultiByOpenid($openids, $platform, $fields = null)
    {
        if (!$openids || !is_array($openids)) return array();

        $where = array('openid' => array('in', $openids), 'platform' => $platform);

        $result = $this->fetchAll($where, $fields);

        return $result ? array_column($result, null, 'openid') : array();
    }

    public function getMaxUid()
    {
        $result = $this->fetchOne(array(), 'MAX(uid) AS max');

        return (int)$result['max'];
    }

    public function getLost($limit, $offset)
    {
        $where = array(
            'regTime' => array('>', date('Y-m-d', time() - 7 * 86400)),
            'lastLoginTime' => array('<', date('Y-m-d', time() - 86400)),
        );
        return $this->fetchAll($where, 'uid,version', null, null, $limit, $offset);
    }

    public function getNotLoginToday($limit, $offset)
    {
        $where = array(
            'lastLoginTime' => array('>', date('Y-m-d', time() - 86400), '<', date('Y-m-d'))
        );
        return $this->fetchAll($where, 'uid,version', null, null, $limit, $offset);
    }

    public function getLoginNearly($fromTime, $limit, $offset)
    {
        $where = array(
            'lastLoginTime' => array('>=', $fromTime)
        );
        return $this->fetchAll($where, 'uid', null, null, $limit, $offset);
    }
}