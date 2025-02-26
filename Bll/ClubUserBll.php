<?php

namespace FF\Bll;

use FF\Factory\Model;

class ClubUserBll
{
    public function getInfo($uid)
    {
        return Model::clubUsers()->getOneById($uid);
    }

}