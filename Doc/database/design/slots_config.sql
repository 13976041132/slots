SET NAMES utf8;

-- 数据库
CREATE DATABASE IF NOT EXISTS slots_config DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

use slots_config;

CREATE TABLE IF NOT EXISTS `t_machine` (
  `machineId` int(10) NOT NULL COMMENT '机台ID',
  `name` varchar(32) NOT NULL COMMENT '机台名称',
  `className` varchar(32) NOT NULL COMMENT '机台类名',
  `cols` int(10) unsigned NOT NULL COMMENT '总列数',
  `rows` int(10) unsigned NOT NULL COMMENT '总行数',
  `winMultiples` int(10) NOT NULL DEFAULT '0' COMMENT '解锁等级',
  `unlockLevel` varchar(64) NOT NULL COMMENT '中奖特殊倍数',
  `options` text NOT NULL COMMENT '其它选项',
  PRIMARY KEY (`machineId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='机台表';

CREATE TABLE IF NOT EXISTS `t_machine_item` (
  `elementId` varchar(16) NOT NULL COMMENT '元素ID',
  `machineId` int(10) NOT NULL COMMENT '机台ID',
  `iconType` varchar(8) NOT NULL COMMENT '图案类型',
  `iconDescription` varchar(32) NOT NULL COMMENT '元素描述',
  `iconImage` varchar(32) NOT NULL DEFAULT '' COMMENT '元素图片名',
  `iconEffect` varchar(32) NOT NULL DEFAULT '' COMMENT '元素特效名',
  `options` varchar(255) NOT NULL DEFAULT '' COMMENT '选项',
  PRIMARY KEY (`machineId`,`elementId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='机台元素表';

CREATE TABLE IF NOT EXISTS `t_payline` (
  `machineId` int(10) NOT NULL COMMENT '机台ID',
  `seq` int(10) unsigned NOT NULL COMMENT 'line序号',
  `route` varchar(64) NOT NULL COMMENT 'line路由',
  PRIMARY KEY (`machineId`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='机台中奖线路表';

CREATE TABLE IF NOT EXISTS `t_paytable` (
  `resultId` varchar(16) NOT NULL COMMENT '组合ID',
  `machineId` int(10) NOT NULL COMMENT '机台ID',
  `elements` varchar(128) NOT NULL COMMENT '中奖元素列表',
  `prize` varchar(16) NOT NULL COMMENT '金币倍数奖励（兼容jackpot标识）',
  `freeSpinOnly` char(1) NOT NULL DEFAULT 'N' COMMENT '是否仅用于freeSpin',
  PRIMARY KEY (`resultId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='机台中奖组合表';

CREATE TABLE IF NOT EXISTS `t_machine_item_reel_weights` (
  `machineId` int(10) NOT NULL COMMENT '机台ID',
  `featureName` varchar(32) NOT NULL DEFAULT '' COMMENT 'featureName',
  `elementId` varchar(16) NOT NULL COMMENT '元素ID',
  `reelWeights` varchar(255) NOT NULL COMMENT 'REEL的权重',
  PRIMARY KEY (`machineId`,`elementId`,`featureName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='机台中奖组合表';

CREATE TABLE IF NOT EXISTS `t_feature_game` (
  `machineId` int(10) NOT NULL COMMENT '机台ID',
  `featureId` varchar(16) NOT NULL COMMENT 'featureId',
  `featureName` varchar(32) NOT NULL DEFAULT '' COMMENT 'featureName',
  `triggerOnline` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否只在line上触发',
  `triggerLines` varchar(64) NOT NULL DEFAULT '' COMMENT '触发时所在line',
  `triggerItems` varchar(255) NOT NULL DEFAULT '' COMMENT '触发时须包含元素(取其中一个)',
  `triggerItemNum` varchar(64) NOT NULL DEFAULT '0' COMMENT '触发时须包含元素的个数',
  `triggerOptions` text NOT NULL COMMENT '触发条件选项',
  `coinsAward` varchar(32) NOT NULL DEFAULT '0' COMMENT '金币奖励',
  `freespinAward` varchar(256) NOT NULL DEFAULT '0' COMMENT 'freespin奖励',
  `multipleAward` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '倍数奖励',
  `itemAward` varchar(255) NOT NULL DEFAULT '0' COMMENT '奖励元素',
  `itemAwardLimit` varchar(1000) NOT NULL DEFAULT '{}' COMMENT '奖励元素约束',
  `extraTimes` varchar(256) NOT NULL DEFAULT '0' COMMENT 'freespin奖励',
  `priority` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '优先级',
  `breakSpin` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否中断Spin',
  `chooseMode` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否是选择模式',
  `version` varchar(8) NOT NULL DEFAULT '' COMMENT '适用版本',
  PRIMARY KEY (`featureId`),
  KEY `machineId` (`machineId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='FeatureGame配置表';

CREATE TABLE IF NOT EXISTS `t_version` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `buildId` int(10) UNSIGNED NOT NULL COMMENT '构建ID',
  `machineId` int(10) NOT NULL COMMENT '机台ID(兼容)',
  `bigVersion` varchar(8) NOT NULL DEFAULT '1.0' COMMENT '大版本号',
  `version` int(10) UNSIGNED NOT NULL COMMENT '小版本号',
  `filePath` varchar(255) NOT NULL COMMENT '存储路径',
  `packages` text COMMENT '更新包信息',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '状态 0-未发布 1-预发布 2-已发布 3-已撤回',
  `createTime` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY version (`machineId`, `bigVersion`, `version`),
  KEY buildId (`buildId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='版本配置表';