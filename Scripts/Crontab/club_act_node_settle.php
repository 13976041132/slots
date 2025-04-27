<?php

namespace FF\Scripts\Crontab;

use FF\Bll\ClubBll;
use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Framework\Utils\Log;

$key = Keys::clubNodeCompList();
while ($row = Dao::redis()->lPop($key)) {
    $row = json_decode($row, true);
    if (!$row || !isset($row['clubId']) || !isset($row['type'])) {
        continue;
    }

    switch ($row['type']) {
        case ClubBll::CLUB_REWARD_TYPE_PIECE_NODE:
            pieceNodeSettle($row);
            break;
        case ClubBll::CLUB_REWARD_TYPE_GAME_POINT_RANK:
            machineNodeSettle($row);
        case ClubBll::CLUB_REWARD_TYPE_CHEST_RANK:
            chestNodeSettle($row);
            break;
    }
}
function pieceNodeSettle($row)
{
    try {
        $clubInfo = Model::clubs()->fetchOne($row['clubId']);
        if (!$clubInfo) {
            Log::error('pieceNodeSettle: club not exist' . var_export($row, true), 'act_node_settle.log');
            return;
        }

        if (!isset($row['collectInfo']) || !is_array($row['collectInfo'])) {
            return;
        }
        $collectInfo = $row['collectInfo'];
        $nodeConfig = Bll::clubOption()->getPuzzleNode($row['node']);
        if (!$nodeConfig) {
            return;
        }
        $clubId = $row['clubId'];
        $type = ClubBll::CLUB_REWARD_TYPE_PIECE_NODE;
        $set = Bll::club()->makeClubRewardSet($type);
        $rewardTime = Bll::clubOption()->getClubRewardTime($type, $clubInfo['level']);
        $expireTime = strtotime(date('Y-m-d')) + $rewardTime * 3600;
        $userRanks = array_count_values($collectInfo);
        $totalScore = count($collectInfo);

        if ($totalScore == 0) {
            Log::error('club reward settle error, totalScore is 0, clubId: ' . $clubId, 'act_node_settle.log');
            return;
        }
        $clubUsersList = Model::clubUsers()->fetchAll(['uid' => ['in', array_keys($userRanks)], 'clubId' => $clubId], 'uid');

        if (!$clubUsersList) {
            Log::error('club reward settle error, clubUsersList is empty, clubId: ' . $clubId, 'act_node_settle.log');
            return;
        }
        $uids = array_column($clubUsersList, 'uid');
        $rate = 1 + Bll::clubOption()->getGradeAdditionValByKey($clubId, 'activityAddition');
        $seasonRewardData = [];
        $pointRewards = [];
        $nodeRewards = array_merge($nodeConfig['nodeRewards'], [$nodeConfig['nodeProps']]);
        foreach ($userRanks as $ruid => $userScore) {
            if (!in_array($ruid, $uids)) {
                Log::error('club reward settle error, uid not in clubUsersList, clubId: ' . $clubId . ', uid: ' . $ruid, 'act_node_settle.log');
                continue;
            }

            $itemList = [];
            $coins = 0;
            foreach ($nodeRewards as $reward) {
                $count = ceil($reward['count'] * min($userScore / $totalScore, 1) * $rate);
                if ($reward['itemId'] == ITEM_POINTS) {
                    $pointRewards[$ruid] = $reward['count'];
                    continue;
                }
                $itemList[] = ['id' => $reward['itemId'], 'num' => $count];
                if ($reward['itemId'] == ITEM_COIN) {
                    $coins += $reward['count'];
                }
            }
            $seasonRewardData[] = [
                'set' => $set,
                'clubId' => $clubId,
                'uid' => $ruid,
                'totalpoints' => $totalScore,
                'points' => $userScore,
                'type' => ClubBll::CLUB_REWARD_TYPE_PIECE_NODE,
                'totalCoin' => $coins,
                'itemList' => json_encode($itemList),
                'expireTime' => $expireTime,
                'progress' => (int)$row['node'],
                'createTime' => date('Y-m-d H:i:s'),
            ];

            $reportData = [
                'club_num' => (int)$clubInfo['memberCnt'],
                'club_condition' => (int)$clubInfo['vipLimit'],
                'club_attribute' => (int)$clubInfo['type'],
                'club_name' => $clubInfo['clubName'],
                'club_level' => (int)$clubInfo['level'],
                'club_id' => (int)$clubId,
                'chest_id' => (int)$row['node'],
                'player_id' => (int)$ruid,
                'club_points' => (int)$pointRewards[$ruid] ?? 0,
                'coins' => (int)$coins,
                'coins_value' => (int)$coins,
            ];
            Bll::shushu()->asyncReport('Club_Activity_Events', $ruid, $reportData);
        }

        Model::clubRewards()->insertMulti($seasonRewardData);

        foreach ($pointRewards as $_uid => $_points) {
            Bll::club()->updateSeasonPoints($_uid, $clubInfo, $_points);
        }
    } catch (\Exception $e) {
        Log::error('pieceNodeSettle: ' . $e->getMessage() . ', ' . $e->getTraceAsString(), 'act_node_settle.log');
    }
}

function machineNodeSettle($row)
{
    try {
        $clubInfo = Model::clubs()->fetchOne($row['clubId']);
        if (!$clubInfo) {
            Log::error('machineNodeSettle: club not exist' . var_export($row, true), 'act_node_settle.log');
            return;
        }

        if (empty($row['collectInfo']) || !is_array($row['collectInfo'])) {
            return;
        }

        if (empty($row['extData']) || !is_array($row['extData'])) {
            return;
        }

        $nodeConfig = Bll::clubOption()->getEventNode($row['node']);
        if (!$nodeConfig) {
            return;
        }

        $userRanks = $row['collectInfo'];
        $clubId = $row['clubId'];
        $type = ClubBll::CLUB_REWARD_TYPE_GAME_POINT_RANK;
        $set = Bll::club()->makeClubRewardSet($type);
        $rewardTime = Bll::clubOption()->getClubRewardTime($type, $clubInfo['level']);
        $expireTime = strtotime(date('Y-m-d')) + $rewardTime * 3600;
        $totalScore = array_sum($userRanks);
        if ($totalScore == 0) {
            Log::error('club reward settle error, totalScore is 0, clubId: ' . $clubId, 'act_node_settle.log');
            return;
        }
        $clubUsersList = Model::clubUsers()->fetchAll(['uid' => ['in', array_keys($userRanks)], 'clubId' => $clubId], 'uid');

        if (!$clubUsersList) {
            Log::error('club reward settle error, clubUsersList is empty, clubId: ' . $clubId, 'act_node_settle.log');
            return;
        }
        $uids = array_column($clubUsersList, 'uid');
        $rate = 1 + Bll::clubOption()->getGradeAdditionValByKey($clubId, 'activityAddition');
        $seasonRewardData = [];
        $pointRewards = [];
        $nodeRewards = array_merge($nodeConfig['nodeReward'], [$nodeConfig['nodeProps']]);
        foreach ($userRanks as $ruid => $userScore) {
            if (!in_array($ruid, $uids)) {
                Log::error('club reward settle error, uid not in clubUsersList, clubId: ' . $clubId . ', uid: ' . $ruid, 'act_node_settle.log');
                continue;
            }

            $itemList = [];
            $coins = 0;
            foreach ($nodeRewards as $reward) {
                $count = ceil($reward['count'] * min($userScore / $totalScore, 1) * $rate);
                if ($reward['itemId'] == ITEM_POINTS) {
                    $pointRewards[$ruid] = $reward['count'];
                    continue;
                }
                $itemList[] = ['id' => $reward['itemId'], 'num' => $count];
                if ($reward['itemId'] == ITEM_COIN) {
                    $coins += $reward['count'];
                }
            }
            $seasonRewardData[] = [
                'set' => $set,
                'clubId' => $clubId,
                'uid' => $ruid,
                'totalpoints' => $totalScore,
                'points' => $userScore,
                'type' => ClubBll::CLUB_REWARD_TYPE_GAME_POINT_RANK,
                'totalCoin' => $coins,
                'itemList' => json_encode($itemList),
                'expireTime' => $expireTime,
                'progress' => (int)$row['node'],
                'createTime' => date('Y-m-d H:i:s'),
                'extData' => json_encode([$row['extData']])
            ];

            $reportData = [
                'club_id' => (int)$clubId,
                'club_num' => (int)$clubInfo['memberCnt'],
                'club_condition' => (int)$clubInfo['vipLimit'],
                'club_attribute' => (int)$clubInfo['type'],
                'club_name' => $clubInfo['clubName'],
                'club_level' => (int)$clubInfo['level'],
                'chest_id' => (int)$row['node'],
                'player_id' => (int)$ruid,
                'club_points' => (int)$pointRewards[$ruid] ?? 0,
                'coins' => (int)$coins,
                'coins_value' => (int)$coins,
            ];
            Bll::shushu()->asyncReport('Club_Activity_Games', $ruid, $reportData);
        }

        Model::clubRewards()->insertMulti($seasonRewardData);

        foreach ($pointRewards as $_uid => $_points) {
            Bll::club()->updateSeasonPoints($_uid, $clubInfo, $_points);
        }
    } catch (\Exception $e) {
        Log::error('machineNodeSettle: ' . $e->getMessage() . ', ' . $e->getTraceAsString(), 'act_node_settle.log');
    }
}

function chestNodeSettle($row)
{
    try {
        $clubInfo = Model::clubs()->fetchOne($row['clubId']);
        if (!$clubInfo) {
            Log::error('machineNodeSettle: club not exist' . var_export($row, true), 'act_node_settle.log');
            return;
        }

        if (empty($row['collectInfo']) || !is_array($row['collectInfo'])) {
            return;
        }

        $nodeConfig = Bll::clubOption()->getChestNode($row['node']);
        if (!$nodeConfig) {
            return;
        }

        $userRanks = $row['collectInfo'];
        $clubId = $row['clubId'];
        $type = ClubBll::CLUB_REWARD_TYPE_CHEST_RANK;
        $set = Bll::club()->makeClubRewardSet($type);
        $rewardTime = Bll::clubOption()->getClubRewardTime($type, $clubInfo['level']);
        $expireTime = strtotime(date('Y-m-d')) + $rewardTime * 3600;
        $totalScore = array_sum($userRanks);
        if ($totalScore == 0) {
            Log::error('club reward settle error, totalScore is 0, clubId: ' . $clubId, 'act_node_settle.log');
            return;
        }
        $clubUsersList = Model::clubUsers()->fetchAll(['uid' => ['in', array_keys($userRanks)], 'clubId' => $clubId], 'uid');

        if (!$clubUsersList) {
            Log::error('club reward settle error, clubUsersList is empty, clubId: ' . $clubId, 'act_node_settle.log');
            return;
        }
        $uids = array_column($clubUsersList, 'uid');
        $seasonRewardData = [];
        $rate = 1 + Bll::clubOption()->getGradeAdditionValByKey($clubId, 'chestAddition');
        foreach ($userRanks as $ruid => $userScore) {
            if (!in_array($ruid, $uids)) {
                Log::error('club reward settle error, uid not in clubUsersList, clubId: ' . $clubId . ', uid: ' . $ruid, 'act_node_settle.log');
                continue;
            }
            $itemList = [];
            $coins = ceil($nodeConfig['chestCoin'] * min($userScore / $totalScore, 1) * $rate);
            $itemList[] = ['id' => ITEM_COIN, 'num' => $coins];
            foreach ($nodeConfig['chestProps'] as $reward) {
                $count = ceil($reward['count'] * $rate);
                $itemList[] = ['id' => $reward['itemId'], 'num' => $count];
                if ($reward['itemId'] == ITEM_COIN) {
                    $coins += $reward['count'];
                }
            }
            $seasonRewardData[] = [
                'set' => $set,
                'clubId' => $clubId,
                'uid' => $ruid,
                'totalpoints' => $totalScore,
                'points' => $userScore,
                'type' => ClubBll::CLUB_REWARD_TYPE_CHEST_RANK,
                'totalCoin' => $coins,
                'itemList' => json_encode($itemList),
                'expireTime' => $expireTime,
                'progress' => (int)$row['node'],
                'createTime' => date('Y-m-d H:i:s'),
            ];

            $reportData = [
                'club_name' => $clubInfo['clubName'],
                'club_id' => (int)$clubId,
                'chest_id' => (int)$row['node'],
                'player_id' => (int)$ruid,
                'coins' => (int)$coins,
                'coins_value' => (int)$coins,
                'club_num' => (int)$clubInfo['memberCnt'],
                'club_level' => (int)$clubInfo['level'],
                'club_condition' => (int)$clubInfo['vipLimit'],
                'club_attribute' => (int)$clubInfo['type']
            ];
            Bll::shushu()->asyncReport('Club_Center_Chest', $ruid, $reportData);
        }

        Model::clubRewards()->insertMulti($seasonRewardData);
    } catch (\Exception $e) {
        Log::error('chestNodeSettle: ' . $e->getMessage() . ', ' . $e->getTraceAsString(), 'act_node_settle.log');
    }
}