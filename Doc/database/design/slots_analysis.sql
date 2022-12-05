SET NAMES utf8;

-- 数据库
CREATE DATABASE IF NOT EXISTS slots_analysis DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

use slots_analysis;

CREATE TABLE IF NOT EXISTS `t_user_bet_data` (
  `uid` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
  `date` date NOT NULL COMMENT '日期',
  `machineId` int(10) unsigned DEFAULT '0' COMMENT '机台ID',
  `totalBet` bigint(20) unsigned DEFAULT '0' COMMENT '下注额度',
  `betTimes` int(10) unsigned DEFAULT '0' COMMENT '下注次数',
  PRIMARY KEY (`uid`,`date`,`machineId`,`totalBet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户下注额数据';