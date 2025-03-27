<?php

use FF\Factory\Model;

$expireTime = time() - 86400;
Model::clubRewards()->delete(['expireTime' => ['<', $expireTime]],0);
Model::clubPublishHelpData()->delete(['expireTime' => ['<', $expireTime]],0);