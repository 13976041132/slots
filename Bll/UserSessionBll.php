<?php /** @noinspection ALL */

/**
 * 用户Session业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;

class UserSessionBll
{
    public function createSession($uid, $token = null, &$sessionData = null)
    {
        //清除旧session
        $this->clearSession($uid);

        $userInfo = Bll::user()->getCacheData($uid);

        if (!$userInfo || empty($userInfo['token'])) return null;

        if ($token && $userInfo['token'] !== $token) return null;

        $sessionData = array(
            'uid' => $uid,
            'version' => $userInfo['appVersion'] ?? 0,
            'sessionId' => md5($userInfo['token']),
            'gameHistoryQueue' => QUEUE_GAME_HISTORY_PREFIX . rand(1, 5),
        );
        $sessionId = Bll::session()->create($uid, $sessionData, 5 * 86400, md5($userInfo['token']));

        $this->setSessionId($uid, $sessionId);

        return $sessionId;
    }

    /**
     * 获取用户当前的sessionId
     */
    public function getSessionId($uid)
    {
        if ($sessionId = Bll::session()->getSessionId()) {
            if (Bll::session()->get('uid') == $uid) {
                return $sessionId;
            }
        }

        $key = Keys::sessionId($uid);

        return Dao::redis()->get($key);
    }

    /**
     * 设置用户当前的sessionId
     */
    public function setSessionId($uid, $sessionId)
    {
        $key = Keys::sessionId($uid);

        return Dao::redis()->set($key, $sessionId);
    }

    /**
     * 清除用户当前的session
     */
    public function clearSession($uid)
    {
        if (Bll::session()->getSessionId()) {
            Bll::session()->clean();
        }

        $key = Keys::sessionId($uid);
        $sessionId = Dao::redis()->get($key);

        if ($sessionId) {
            Bll::session()->destroy($sessionId);
        }
    }

    /**
     * 获取用户session
     */
    public function getSession($uid, $key = null)
    {
        $data = Bll::session()->getSessionData();
        if ($data && $data['uid'] == $uid) {
            if ($key) {
                if (is_array($key)) {
                    return array_recombine($data, $key);
                } else {
                    return $data[$key];
                }
            } else {
                return $data;
            }
        }

        $sessionId = $this->getSessionId($uid);
        if (!$sessionId) return null;

        $cacheKey = Keys::session($sessionId);

        if ($key) {
            if (is_array($key)) {
                return Dao::redis()->hMGet($cacheKey, $key);
            } else {
                return Dao::redis()->hGet($cacheKey, $key);
            }
        } else {
            return Dao::redis()->hGetAll($cacheKey);
        }
    }

    /**
     * 获取批量用户session列表
     */
    public function getSessionList($uids, $keys = null)
    {
        $sessions = array();
        if (empty($uids)) return [];

        if ($keys && is_string($keys)) {
            $keys = explode(',', str_replace(' ', '', $keys));
        }
        if ($keys && !in_array('uid', $keys)) {
            array_unshift($keys, 'uid');
        }

        //单个玩家不走pipeline
        if (!is_array($uids)) {
            $uid = $uids;
            $info = $this->getSession($uid, $keys);
            if ($info) {
                $sessions[$uid] = $info;
            }
            return $sessions;
        }

        $sessionIdKeys = [];
        foreach ($uids as $uid) {
            $sessionIdKeys[] = Keys::sessionId($uid);
        }

        $sessionIdList = Dao::redis()->mGet($sessionIdKeys);

        // 通过pipeline批量获取session数据
        $pipeline = Dao::redis()->pipeline();
        foreach ($sessionIdList as $sessionId) {
            if (!$sessionId) continue;
            $cacheKey = Keys::session($sessionId);
            if ($keys) {
                $pipeline->hMGet($cacheKey, $keys);
            } else {
                $pipeline->hGetAll($cacheKey);
            }
        }
        $result = $pipeline->exec();

        foreach ($result as $info) {
            if (!$info) continue;
            $sessions[$info['uid']] = $info;
        }

        return $sessions;
    }
}