<?php

namespace FF\Bll;

class LoginUserBll
{
    protected $userInfo;
    public function get($key)
    {
        return $this->userInfo[$key] ?? null;
    }

    public function setUserInfo($userInfo)
    {
        return $this->userInfo = $userInfo;
    }
}