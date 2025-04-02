<?php

namespace FF\Scripts\Crontab;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Utils\Config;

$seasonId = Bll::clubOption()->getSeasonId();

if (!$seasonId) {
    return;
}
$seasonInfo = Config::get('club/season', $seasonId, false);
if (!$seasonInfo) {
    return;
}

$leagueCd = strtotime($seasonInfo['seasonEnd']) - time() - 86399;
$config = Config::get('club/grade');
foreach ($config as $gradeId => $row) {
    $type = Bll::rank()->getClubType($gradeId, $seasonId);
    $ranks = Bll::rank()->getList($type, 0, 150);
    if (!$ranks) {
        continue;
    }

    $clubList = Model::clubs()->fetchAll(['clubId' => ['in', array_keys($ranks)], 'ai' => 0]);
    $clubList = array_column($clubList, null, 'clubId');
    $rank = 0;
    foreach ($ranks as $clubId => $score) {
        ++$rank;
        if (Bll::club()->isAiClub($clubId)) {
            continue;
        }
        if (!isset($clubList[$clubId])) {
            continue;
        }
        $clubInfo = $clubList[$clubId];
        $reportData = [
            'club_information' => json_encode(['club_id' => $clubId, 'club_name' => $clubInfo['clubName']]),
            'club_id' => (int)$clubId,
            'club_level' => (int)$clubInfo['level'],
            'club_rank' => $gradeId + 1,
            'rank' => (int)$rank,
            'club_points' => (int)$score,
            'season' => $seasonId,
            'League_cd' => max(0, $leagueCd),
        ];

        Bll::shushu()->asyncReport('Club_Center_League', $clubId, $reportData);
    }
}