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
    `uid` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
    `fuid` int(10) UNSIGNED NOT NULL COMMENT '好友用户ID',
    `createTime` datetime DEFAULT NULL COMMENT '创建时间',
    `unReadCnt` int(10) UNSIGNED NOT NULL COMMENT '未读的消息数量',
    PRIMARY KEY (`uid`, `fuid`)
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