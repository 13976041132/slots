<?php
/**
 * session业务层
 */

namespace FF\Bll;

use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Input;

class SessionBll
{
    private $sessionId = '';

    private $sessionData = array();

    private $cookieKey = '';

    public function setCookieKey($cookieKey)
    {
        $this->cookieKey = $cookieKey;
    }

    public function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function getSessionId()
    {
        if (!$this->sessionId && $this->cookieKey) {
            $this->sessionId = Input::cookie($this->cookieKey);
        }

        return $this->sessionId;
    }

    public function getSessionData()
    {
        if ($this->sessionData) {
            return $this->sessionData;
        }

        $sessionId = $this->getSessionId();

        if (!$sessionId) return array();

        $key = Keys::session($this->sessionId);
        $sessionData = Dao::redis()->hGetAll($key);

        if (!$sessionData) {
            $this->clean();
            return array();
        }

        $this->sessionData = $sessionData;

        return $this->sessionData;
    }

    public function create($uid, $data, $expire = 3 * 86400)
    {
        $this->clean();

        $sessionId = md5($uid . ':' . time() . ':' . mt_rand(1000, 9999));

        if ($this->cookieKey) {
            setcookie($this->cookieKey, $sessionId, time() + $expire, '/', null, null, true);
        }

        $this->sessionId = $sessionId;

        $result = $this->save($data, $expire);
        if (!$result) {
            FF::throwException(Code::SYSTEM_BUSY);
        }

        return $this->sessionId;
    }

    public function destroy($sessionId)
    {
        $key = Keys::session($sessionId);

        return Dao::redis()->del($key);
    }

    public function save($data, $expire = null)
    {
        if (!$this->sessionId) return false;
        if (!$data || !is_array($data)) return false;

        $key = Keys::session($this->sessionId);
        $result = Dao::redis()->hMset($key, $data);

        if ($result) {
            $sessionData = $this->getSessionData();
            $this->sessionData = array_merge($sessionData, $data);
            if ($expire) Dao::redis()->expire($key, $expire);
        }

        return $result;
    }

    public function clean()
    {
        if ($this->cookieKey) {
            setcookie($this->cookieKey, '', time() - 1, '/', null, null, true);
        }
        $this->sessionId = '';
        $this->sessionData = array();
    }

    public function get($key)
    {
        $session = $this->getSessionData();

        return $session && isset($session[$key]) ? $session[$key] : null;
    }
}