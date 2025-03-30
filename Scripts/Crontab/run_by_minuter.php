<?php
/**
 * 每分钟执行一次的脚本
 */

include __DIR__ . '/../common.php';

//include __DIR__ . '/init_ai_club_data.php';
//include __DIR__ . '/ai_club_season_points_incr.php';
include __DIR__ . '/sync_suggest_club.php';
include __DIR__ . '/club_act_node_settle.php';