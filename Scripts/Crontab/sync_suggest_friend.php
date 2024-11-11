<?php

namespace FF\Scripts\Crontab;

use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;

//查询近一周玩家登录的玩家
$start = date("Y-m-d", time() - 7 * 86400);
//清空对应的表
Dao::db()->query("truncate table suggest_users");

$sSql = "SELECT uid from user_daily_first_login_log where date >='{$start}' group by uid HAVING COUNT(uid) >=3  order by uid";
$iSql = "INSERT INTO suggest_users ($sSql)";

Dao::db()->query($iSql);

$dsql = "delete from suggest_users where uid in (SELECT uid  FROM friends group by uid HAVING COUNT(uid) >=100)";

//判断目标用户是否满好友
Dao::db()->query($dsql);

$info = Model::suggestUser()->fetchOne([], 'count(1) count');

if ($info['count'] == 0) {
    return;
}

$count = $info['count'];
$count = min($count, 10000);
$limit = 1000;
$offset = 0;
$key = Keys::suggestFriendSet();
Dao::redis()->del($key);
while ($count > 0) {
    $limit = min($count, $limit);
    $list = Model::suggestUser()->fetchAll([], 'uid', [], '', $limit, $offset);
    $uids = array_column($list, 'uid');
    Dao::redis()->sAdd($key, ...$uids);
    $offset += $limit;
    $count -= $limit;
}