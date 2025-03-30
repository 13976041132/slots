<?php

namespace FF\Scripts\Crontab;

use FF\Bll\ClubBll;
use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Utils\Config;

//获取参数
$init = $argv[1] ?? false;

if (date('H') != '00' && !$init) {
    return;
}

$id = Bll::clubOption()->getSeasonId();

if (!$id) {
    return;
}

$seasonInfo = Config::get('club/season', $id, false);
if (!$init && (!$seasonInfo || $seasonInfo['seasonStart'] != date('Y-m-d'))) {
    return;
}

$gradeConfig = Config::get('club/grade', $id - 1, false);
if (!$gradeConfig) {
    return;
}
//根据赛季俱乐部信息
$commCfg = Config::get('club/common');

$num = (int)array_sum($commCfg['scale']);
if ($num <= 0) return;
$aiClubs = [];
$aiCfg = Config::get('club/ai');
$levelCfg = Config::get('club/level');
$cntLevel = array_column($levelCfg, 'clubLevel', 'member');
foreach ($aiCfg as $aiInfo) {
    if ($aiInfo['grade'] != $gradeConfig['grade']) {
        continue;
    }
    $level = $aiInfo['level'];
    if (!isset($commCfg['scale'][$level - 1])) {
        continue;
    }
    $cunt = ceil($commCfg['aiClub'] * $commCfg['scale'][$level - 1] / $num);

    for ($i = 0; $i < $cunt; $i++) {
        $ukey = (int)($aiInfo['id'] . ($level + $i));
        if (!Bll::club()->isAiClub($ukey)) {
            continue;
        }
        $aiClubs[] = [
            'clubId' => $ukey,
            'creator' => $ukey,
            'dan' => $gradeConfig['id'],
            'type' => ClubBll::TYPE_PRIVATE,
            'ai' => 1,
            'level' => $cntLevel[$aiInfo['member']] ?? 1,
            'memberCnt' => $aiInfo['member'],
            'aiActLevel' => $level,
            'clubName' => createNonceStr(15),
        ];
    }
}

if ($aiClubs) {
    Model::clubs()->insertMulti($aiClubs);
}

