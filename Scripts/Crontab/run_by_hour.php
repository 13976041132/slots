<?php
/**
 * 每小时执行一次的脚本
 */

include __DIR__ . '/../common.php';

include __DIR__ . '/init_club_season_data.php';

include __DIR__ . '/club_user_role_update.php';

include __DIR__ . '/daily_club_reward_settle.php';

include __DIR__ . '/del_club_expire_data.php';

//include __DIR__ . '/sync_suggest_friend.php';

include __DIR__ . '/club_season_rank_report.php';