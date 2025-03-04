<?php

namespace FF\Middleware;

use FF\Factory\Bll;

class CheckSignature
{
    public function handle()
    {
        return Bll::userRequestLast()->checkSignature();
    }
}