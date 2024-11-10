<?php

namespace FF\App\GameMain\Controller;

use FF\Factory\Bll;

class UserController extends BaseController
{
    public function dataReport()
    {
        $uid = $this->getUid();
        $data = $this->getParams();
        Bll::user()->updateUserInfo($uid, $data);

        return [];
    }
}