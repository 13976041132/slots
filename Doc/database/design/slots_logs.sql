SET NAMES utf8;

-- 数据库
CREATE DATABASE IF NOT EXISTS slots_logs DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

use slots_logs;

CREATE TABLE IF NOT EXISTS `t_data_log` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `uid` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
  `field` varchar(16) NOT NULL COMMENT '字段名称',
  `amount` decimal(20,2) SIGNED NOT NULL COMMENT '增加或减少数量',
  `balance` decimal(20,2) UNSIGNED NOT NULL COMMENT '变化后余额',
  `reason` varchar(32) NOT NULL DEFAULT '' COMMENT '变化原因',
  `time` datetime NOT NULL COMMENT '发生时间',
  PRIMARY KEY (`id`),
  KEY `uid`(`uid`),
  KEY `time`(`time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='玩家关键数据变化日志表';

CREATE TABLE IF NOT EXISTS `t_bet_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `betId` varchar(64)  DEFAULT NULL COMMENT '牌局id',
  `uid` int(10) unsigned NOT NULL COMMENT '用户ID',
  `machineId` int(10) NOT NULL COMMENT '机台ID',
  `betSeq` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '下注次序',
  `isNoviceProtect` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否新手保护期',
  `isIntervene` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否干预',
  `interveneType` varchar(16) NOT NULL DEFAULT '' COMMENT '干预类型',
  `interveneNo` varchar(8) NOT NULL DEFAULT '' COMMENT '干预编号',
  `cost` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '消耗金币数量',
  `balance` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '金币余额',
  `betMultiple` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '下注倍数',
  `totalBet` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '下注额',
  `betRatio` bigint(20) unsigned NOT NULL DEFAULT '1' COMMENT '余额下注比',
  `isMaxBet` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '是否是最大Bet',
  `isFreeSpin` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '是否是freespin',
  `isLastFreeSpin` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '是否是最后一次freespin',
  `isReFreeSpin` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '是否是重置freespin',
  `spinTimes` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '第几次Spin',
  `stickyElements` text NOT NULL COMMENT 'stickyElements',
  `steps` text NOT NULL COMMENT '中奖结果',
  `extra` text NOT NULL COMMENT '扩展信息（用于后台展示）',
  `feature` varchar(16) NOT NULL DEFAULT '' COMMENT '当前feature',
  `featureNo` varchar(16) NOT NULL DEFAULT '' COMMENT '当前feature编号',
  `features` varchar(255) NOT NULL DEFAULT '' COMMENT '触发的feature列表',
  `featureSteps` text NULL COMMENT 'feature结果(用于后台展示)',
  `coinsAward` bigint(20) NOT NULL DEFAULT '0' COMMENT '金币奖励',
  `freespinAward` int(10) NOT NULL DEFAULT '0' COMMENT 'freespin奖励',
  `multipleAward` int(10) NOT NULL DEFAULT '0' COMMENT '倍数奖励',
  `totalWin` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '总赢得金币',
  `jackpotWin` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'jackpot赢得金币',
  `settled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否已结算',
  `version` varchar(16) NOT NULL DEFAULT '' COMMENT '当前版本',
  `level` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '当前等级',
  `time` datetime NOT NULL COMMENT '下注时间',
  `microtime` bigint(20) unsigned NOT NULL COMMENT '下注时间戳（微秒）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `betId` (`betId`),
  KEY `uid` (`uid`),
  KEY `machineId` (`machineId`),
  KEY `time` (`time`),
  KEY `microtime` (`microtime`)
) ENGINE=MyISAM AUTO_INCREMENT=4315 DEFAULT CHARSET=utf8 COMMENT='玩家下注记录表';

CREATE TABLE IF NOT EXISTS `t_event_log` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `uid` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
  `event` varchar(64) NOT NULL COMMENT '事件',
  `extra1` varchar(32) NOT NULL DEFAULT '' COMMENT '扩展属性1',
  `extra2` varchar(32) NOT NULL DEFAULT '' COMMENT '扩展属性2',
  `extra3` varchar(32) NOT NULL DEFAULT '' COMMENT '扩展属性3',
  `extra4` varchar(32) NOT NULL DEFAULT '' COMMENT '扩展属性4',
  `extra5` varchar(32) NOT NULL DEFAULT '' COMMENT '扩展属性5',
  `extra6` varchar(32) NOT NULL DEFAULT '' COMMENT '扩展属性6',
  `extra7` varchar(32) NOT NULL DEFAULT '' COMMENT '扩展属性7',
  `extra8` varchar(32) NOT NULL DEFAULT '' COMMENT '扩展属性8',
  `time` datetime NOT NULL COMMENT '发生时间',
  PRIMARY KEY (`id`),
  KEY `event` (`event`, `time`, `uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='事件日志表';

CREATE TABLE IF NOT EXISTS `t_api_log` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `uid` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
  `msgId` int(10) UNSIGNED NOT NULL COMMENT '消息ID',
  `times` int(10) UNSIGNED NOT NULL COMMENT '请求次数',
  `cost` int(10) UNSIGNED NOT NULL COMMENT '总耗时',
  `time` datetime NOT NULL COMMENT '发生时间',
  PRIMARY KEY (`id`),
  KEY `msgId` (`time`, `msgId`, `uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='API日志表';

CREATE TABLE IF NOT EXISTS `t_operation_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `appId` tinyint(1) NOT NULL DEFAULT 0 COMMENT '应用ID',
  `category` varchar(32) NOT NULL COMMENT '操作类别',
  `target` varchar(64) NOT NULL COMMENT '操作对象',
  `action` varchar(64) NOT NULL COMMENT '操作行为',
  `content` text COMMENT '操作内容描述',
  `user` varchar(16) NOT NULL COMMENT '操作人',
  `time` datetime NOT NULL COMMENT '操作时间',
  `ip` varchar(16) NOT NULL COMMENT 'IP地址',
  PRIMARY KEY (`id`),
  KEY `category_target` (`appId`, `category`, `target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='操作记录日志';

CREATE TABLE IF NOT EXISTS `d_max_balance_point` (
  `id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '记录ID',
  `uid` int(10) unsigned NOT NULL COMMENT '用户ID',
  `balance` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '金币余额',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT '测试中间表-玩家资产余额达到顶峰时的spin次数';