SET NAMES utf8;

-- 数据库
CREATE DATABASE IF NOT EXISTS slots_test DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

use slots_test;

CREATE TABLE IF NOT EXISTS `t_slots_test` (
  `testId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `machineId` int(10) NOT NULL COMMENT '待执行机台ID',
  `machineIds` varchar(64) NOT NULL COMMENT '机台列表(多机台逗号分隔)',
  `userLevel` int(10) UNSIGNED NOT NULL COMMENT '用户等级',
  `totalBet` int(10) UNSIGNED NOT NULL COMMENT '下注额',
  `betGrade` tinyint(3) UNSIGNED NOT NULL DEFAULT '0' COMMENT '下注档位',
  `betTimes` int(10) UNSIGNED NOT NULL COMMENT '总下注次数',
  `betUsers` int(10) UNSIGNED NOT NULL COMMENT '下注人数',
  `perBetTimes` varchar(64) NOT NULL COMMENT '每人下注次数(多机台逗号分隔)',
  `initCoins` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '初始金币数',
  `isNovice` char(1) NOT NULL DEFAULT 'A' COMMENT '是否新手[Y|N|A]',
  `betAutoRaise` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否自动升Bet',
  `featureOpened` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '是否开启feature',
  `ivOpened` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否开启干预',
  `bettedTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '已下注次数',
  `bettedUsers` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '已完成下注人数',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '状态 0-未启动 1-执行中 2-执行完毕 3-排队中',
  `createTime` datetime NOT NULL COMMENT '创建时间',
  `startTime` datetime DEFAULT NULL COMMENT '启动时间',
  `endTime` datetime DEFAULT NULL COMMENT '结束时间',
  `error` text DEFAULT NULL COMMENT '错误信息',
  `logPath` varchar(256) NOT NULL DEFAULT '' COMMENT 'spin日志文件路径',
  `testers` text COMMENT '测试用户列表',
  `stats` text COMMENT '统计信息',
  PRIMARY KEY (`testId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT 'Slots测试计划表';