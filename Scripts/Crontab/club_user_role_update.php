<?php

namespace FF\Scripts\Crontab;

use FF\Factory\Dao;

$sql = 'update clubs t join (SELECT 
    `clubId`, 
    `uid` AS `topDonor`
FROM (
    SELECT 
        `clubId`, 
        `uid`, 
        `coins`, 
        ROW_NUMBER() OVER (PARTITION BY `clubId` ORDER BY `coins` DESC) AS `rank`
    FROM `club_users`
) AS `ranked_users`
WHERE `rank` = 1) b on t.clubId = b.clubId set t.topDonor = b.topDonor';

Dao::db()->query($sql);