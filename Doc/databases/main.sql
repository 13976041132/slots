CREATE TABLE IF NOT EXISTS `friends_requests` (
    `uuid` varchar(20) NOT NULL COMMENT '唯一ID',
    `uid` int(10) unsigned NOT NULL COMMENT '发送申请用户ID',
    `fuid` int(10) unsigned NOT NULL COMMENT '接收申请用户ID',
    `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态(0申请；1接受；2拒绝)',
    `createTime` datetime DEFAULT NULL COMMENT '创建时间',
    `requestTime` datetime DEFAULT NULL COMMENT '申请时间',
    PRIMARY KEY (uuid),
    KEY `idx_uid_status` (`uid`,`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='好友申请表';

CREATE TABLE IF NOT EXISTS `friends` (
    `uuid` varchar(20) NOT NULL COMMENT '唯一ID',
    `uid` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
    `fuid` int(10) UNSIGNED NOT NULL COMMENT '好友用户ID',
    `createTime` datetime DEFAULT NULL COMMENT '创建时间',
    `unReadCnt` int(10) UNSIGNED NOT NULL COMMENT '未读的消息数量',
    `givingGiftTimes` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '赠送礼物的次数',
    `receiveGiftTimes` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '接收礼物的次数',
    PRIMARY KEY (`uuid`),
    UNIQUE KEY (`uid`, `fuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='好友关系表';

CREATE TABLE IF NOT EXISTS `chat_log` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `uuid` varchar(20) NOT NULL COMMENT '唯一ID',
    `content` text COMMENT '内容',
    `sender` int(11) NOT NULL  COMMENT '发送者',
    `receiver` int(11) NOT NULL COMMENT '接收者',
    `time` int(11) NOT NULL COMMENT '创建时间',
    `status` int(11) NOT NULL DEFAULT 0 COMMENT '0:未读 1: 已读',
    `microtime` varchar(13) NOT NULL COMMENT '毫秒时间戳',
    PRIMARY KEY (`id`),
    KEY (`uuid`),
    KEY (`microtime`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='聊天记录';

CREATE TABLE IF NOT EXISTS `user_invite_data` (
    `uid` int(11)  NOT NULL COMMENT '用户ID',
    `code` varchar(16) NOT NULL COMMENT '邀请码',
    `invitedBy` int(11) NULL COMMENT '邀请者',
    `inviteUids`text COMMENT '邀请的uid, 逗号分割',
    `inviteCnt` int(11) NOT NULL DEFAULT 0 COMMENT '邀请的数量',
    `updateTime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL COMMENT '更新时间',
    PRIMARY KEY (`uid`),
    UNIQUE KEY `unique_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='玩家邀请数据';

CREATE TABLE IF NOT EXISTS `user_bll_reward_data` (
    `id` int(11)  NOT NULL AUTO_INCREMENT,
    `uid` int(11)  NOT NULL COMMENT '用户ID',
    `messageId` int(11) NOT NULL COMMENT '消息ID',
    `triggerUid` int(11) NOT NULL COMMENT '触发者',
    `itemList` text  COMMENT '奖励信息',
    `status` tinyint(2) NOT NULL DEFAULT '0' COMMENT '0:待领取, 1:已领取',
    `expireTime` int(11) NOT NULL DEFAULT '0' COMMENT '过期时间',
    `time` int(11)  NOT NULL COMMENT '记录时间',
    `updateTime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY (`uid`, `messageId`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户业务奖励数据';

CREATE TABLE IF NOT EXISTS `user_daily_first_login_log` (
    `date` date  NOT NULL COMMENT '日期',
    `uid` int(11)  NOT NULL COMMENT '用户ID',
    PRIMARY KEY (`date`, `uid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户每日首登记录';

CREATE TABLE IF NOT EXISTS `suggest_users` (
    `uid` int(11)  NOT NULL COMMENT '用户ID',
    PRIMARY KEY (`uid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推荐用户';

CREATE TABLE IF NOT EXISTS `user_request_last` (
    `uid` int(11)  NOT NULL COMMENT '用户ID',
    `requestId` varchar(32)  NOT NULL COMMENT '请求ID',
    `secretKey` varchar(32)  NOT NULL COMMENT '秘钥',
    `messageId` int(11)  NOT NULL COMMENT '消息ID',
    `request`   text  COMMENT '请求参数信息',
    `response` text  COMMENT '响应结构',
    `requestTime` int(11) NOT NULL COMMENT '请求时间',
    PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='玩家请求数据';

CREATE TABLE IF NOT EXISTS `clubs` (
    `clubId`   int(11)  NOT NULL AUTO_INCREMENT,
    `creator`  int(11)  NOT NULL COMMENT '创建者',
    `type` int(11)  NOT NULL default 1 COMMENT '1:私有 2:公开',
    `level`    int(11)  NOT NULL default 1 COMMENT '等级',
    `clubName` varchar(32) NOT NULL  COMMENT '俱乐部名称',
    `headId`   int(11)  NOT NULL default 1 COMMENT '头像ID',
    `memberCnt` int(11)  NOT NULL default 1 COMMENT '成员数量',
    `vipLimit`  int(11)  NOT NULL default 0 COMMENT 'vip限制',
    `coins`     BIGINT  NOT NULL default 0 COMMENT '金币',
    `dan`       tinyint(2)  NOT NULL default 0 COMMENT '段位',
    `topDonor` int(11) DEFAULT NULL default 0 COMMENT '捐赠最多的玩家ID',
    `ai`        tinyint(2)  NOT NULL default 0 COMMENT '是否是AI俱乐部',
    `aiActLevel` tinyint(2)  NOT NULL default 0 COMMENT 'AI活跃等级 1:高 2:中 3:低',
    `donateTimes` int(11)  NOT NULL default 0 COMMENT '捐赠次数',
    `createTime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updateTime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL COMMENT '更新时间',
    UNIQUE KEY `unique_creator`(`creator`),
    UNIQUE KEY `unique_club_name` (`clubName`),
    PRIMARY KEY (`clubId`)
    ) ENGINE=InnoDB AUTO_INCREMENT=10000 DEFAULT CHARSET=utf8mb4 COMMENT='俱乐部信息';
CREATE TABLE IF NOT EXISTS `club_users` (
    `clubId`   int(11)  NOT NULL COMMENT '俱乐部ID',
    `uid`      int(11)  NOT NULL COMMENT '用户',
    `role`     int(11)  NOT NULL default 5 COMMENT '1:Leader 2:Co-Leader,3:Donate MVP 4:Points MVP 5:Member',
    `coins`    BIGINT NOT NULL default 0 COMMENT '捐赠的金币',
    `points`   int(11)  NOT NULL COMMENT '积分',
    `muteStatus` int(11)  NOT NULL default 0 COMMENT '0:可以发言 1:禁言',
    `joinTime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '加入的时间',
    `updateTime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL COMMENT '更新时间',
    PRIMARY KEY (`uid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=10000 DEFAULT CHARSET=utf8mb4 COMMENT='俱乐部成员';

CREATE TABLE IF NOT EXISTS `club_chat_log` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `clubId`   int(11)  NOT NULL COMMENT '俱乐部ID',
    `content` text COMMENT '内容',
    `sender` int(11) NOT NULL  COMMENT '发送者',
    `name` varchar(32) NOT NULL DEFAULT ''  COMMENT '名字',
    `headId` int(11) NOT NULL  COMMENT '头像',
    `headFrameId` int(11) NOT NULL  COMMENT '头像框',
    `chatTime` int(11) NOT NULL DEFAULT 0 COMMENT '聊天时间',
    `microtime` varchar(13) NOT NULL COMMENT '毫秒时间戳',
    `updateTime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY (`clubId`),
    KEY (`microtime`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='俱乐部聊天记录';

CREATE TABLE IF NOT EXISTS `club_puzzle_log` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `clubId`   int(11)  NOT NULL COMMENT '俱乐部ID',
    `uid` int(11)  NOT NULL COMMENT '用户',
    `puzzleId` int(11) NOT NULL COMMENT '拼图ID',
    `actDate` int(11) NOT NULL COMMENT '活动日期',
    `time` int(11) NOT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='俱乐部拼图记录';

CREATE TABLE IF NOT EXISTS `user_club_request_log` (
    `uuid` varchar(36) NOT NULL COMMENT 'uuid',
    `clubId` int(11)  NOT NULL COMMENT '俱乐部ID',
    `uid` int(11)  NOT NULL COMMENT '用户ID',
    `invitedBy` int(11) NOT NULL COMMENT '邀请者',
    `status` int(11)  NOT NULL default 1 COMMENT '1:申请中,2.加入,3:拒绝',
    `inviteTime` datetime DEFAULT NULL COMMENT '邀请时间',
    PRIMARY KEY (`uuid`),
    UNIQUE KEY (`clubId`,`uid`,`invitedBy`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='俱乐部邀请记录';

CREATE TABLE IF NOT EXISTS `club_rank_log` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `clubId` int(11)  NOT NULL COMMENT '俱乐部ID',
    `season` int(11)  NOT NULL DEFAULT 0 COMMENT '赛季',
    `rank` int(11)  NOT NULL DEFAULT 0 COMMENT '排名',
    `poins` int(11)  NOT NULL DEFAULT 0 COMMENT '积分',
    `dan` int(11)  NOT NULL DEFAULT 0 COMMENT '段位',
    `rewardCoins` BIGINT NOT NULL DEFAULT 0 COMMENT '奖励的金币',
    `time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '排名时间',
    PRIMARY KEY (`id`),
    KEY (`clubId`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='俱乐部排行榜记录';

CREATE TABLE IF NOT EXISTS `club_rewards` (
    `set` varchar(32) NOT NULL COMMENT '集合id',
    `clubId` int(11)  NOT NULL COMMENT '俱乐部ID',
    `uid` int(11)  NOT NULL DEFAULT 0 COMMENT '用户ID',
    `type` int(11)  NOT NULL COMMENT '类型',
    `progress` int(11)  NOT NULL DEFAULT 0 COMMENT '节点',
    `totalpoints` int(11)  NOT NULL DEFAULT 0 COMMENT '积分',
    `points` int(11)  NOT NULL DEFAULT 0 COMMENT '我的积分',
    `status` int(11)  NOT NULL DEFAULT 0 COMMENT '状态, 0: 未领取 1:领取, 2:不可领取',
    `totalCoin` BIGINT NOT NULL DEFAULT 0 COMMENT '总金币',
    `itemList` text COMMENT '奖励的道具',
    `expireTime` int(11) NOT NULL default 0 COMMENT '过期时间',
    `extData` text COMMENT '额外数据',
    `createTime` TIMESTAMP DEFAULT COMMENT '创建时间',
    `updateTime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL COMMENT '更新时间',
    PRIMARY KEY (`uid`, `set`),
    key (`set`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='俱乐部奖励';

CREATE TABLE IF NOT EXISTS `club_jackpot_log` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `clubId` int(11)  NOT NULL COMMENT '俱乐部ID',
    `uid` int(11)  NOT NULL DEFAULT 0 COMMENT '用户ID',
    `coins` BIGINT NOT NULL DEFAULT 0 COMMENT '奖励的金币',
    `rewardCoins` BIGINT NOT NULL DEFAULT 0 COMMENT '奖励的金币',
    `hitTime` TIMESTAMP NOT NULL COMMENT '时间',
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='jackpot记录';

CREATE TABLE IF NOT EXISTS `club_publish_help_data` (
    `publishId` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `clubId` int(11)  NOT NULL COMMENT '俱乐部ID',
    `uid` int(11)  NOT NULL DEFAULT 0 COMMENT '用户ID',
    `type` int(11) NOT NULL default 1 COMMENT '1:coin 2:邮票',
    `helpers` varchar(255) NOT NULL default '' COMMENT '帮助者',
    `helpLimit` tinyint(2) NOT NULL default 0 COMMENT '上限',
    `itemList` text  COMMENT '奖励信息',
    `status` int(11)  NOT NULL DEFAULT 0 COMMENT '0:进行中, 1:完成 2:失败',
    `expireTime` int(11) NOT NULL default 0 COMMENT '过期时间',
    `createTime` TIMESTAMP DEFAULT COMMENT '创建时间',
    `updateTime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL COMMENT '更新时间',
    PRIMARY KEY (`publishId`),
    key (`clubId`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='发布帮助数据';


