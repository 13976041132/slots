SET NAMES utf8;

-- 数据库
CREATE DATABASE IF NOT EXISTS slots_main DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

use slots_main;

CREATE TABLE IF NOT EXISTS `t_account` (
  `uid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `openid` varchar(64) COLLATE utf8_bin NOT NULL COMMENT '平台账号ID',
  `platform` tinyint(1) UNSIGNED NOT NULL COMMENT '账号所属平台',
  `bindGuest` varchar(64) COLLATE utf8_bin DEFAULT NULL COMMENT '绑定的游客ID',
  `regVersion` varchar(16) NOT NULL DEFAULT '' COMMENT '注册版本',
  `lastLoginVersion` varchar(16) NOT NULL DEFAULT '' COMMENT '最后登录版本',
  `deviceId` varchar(64) NOT NULL DEFAULT '' COMMENT '设备号',
  `deviceToken` varchar(255) NOT NULL DEFAULT '' COMMENT '设备推送Token',
  `deviceScore` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '设备性能评分',
  `idfa` varchar(64) NOT NULL DEFAULT '' COMMENT '广告追踪ID',
  `appsflyerId` varchar(64) NOT NULL DEFAULT '' COMMENT 'appsflyerID',
  `email` varchar(64) DEFAULT NULL COMMENT '邮箱',
  `country` varchar(16) NOT NULL DEFAULT '' COMMENT '所属国家',
  `regTime` datetime NOT NULL COMMENT '注册时间',
  `lastLoginTime` datetime DEFAULT NULL COMMENT '最后登录时间',
  `loginDays` int(10) UNSIGNED NOT NULL DEFAULT '1' COMMENT '总登陆天数',
  `continued` int(10) UNSIGNED NOT NULL DEFAULT '1' COMMENT '连续登陆天数',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '账号状态 1-正常 0-禁用',
  PRIMARY KEY (`uid`),
  UNIQUE KEY `account` (`openid`, `platform`),
  KEY `lastLoginTime` (`lastLoginTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户账号表';

ALTER TABLE t_account AUTO_INCREMENT=10000;

CREATE TABLE IF NOT EXISTS `t_user` (
  `uid` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
  `nickname` varchar(64) NOT NULL DEFAULT '' COMMENT '角色名字',
  `avatar` varchar(128) NOT NULL DEFAULT '' COMMENT '角色头像',
  `platformAvatar` varchar(512) NOT NULL DEFAULT '' COMMENT '平台头像',
  `email` varchar(128) NOT NULL DEFAULT '' COMMENT '邮箱',
  `exp` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '角色经验值',
  `level` int(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '角色等级',
  `vip` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'VIP级别',
  `vipPts` decimal(12,2) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'VIP积分',
  `recharge` decimal(12,2) UNSIGNED NOT NULL DEFAULT '0' COMMENT '付费总额',
  `coins` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '游戏币',
  `diamond` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '钻石',
  `lang` varchar(8) NOT NULL DEFAULT '' COMMENT '选择语言',
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='玩家信息表';


CREATE TABLE IF NOT EXISTS `t_freespin` (
  `uid` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
  `machineId` int(10) NOT NULL COMMENT '机台ID',
  `initTimes` int(10) UNSIGNED NOT NULL COMMENT '初始次数',
  `totalTimes` int(10) UNSIGNED NOT NULL COMMENT '总计次数',
  `spinTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '已Spin次数',
  PRIMARY KEY (`uid`, `machineId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='玩家免费Spin信息表';

CREATE TABLE IF NOT EXISTS `t_game_info` (
  `uid` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
  `machineId` int(10) NOT NULL COMMENT '机台ID',
  `betId` varchar(40) NOT NULL DEFAULT '' COMMENT '当前下注ID',
  `betMultiple` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '当前下注倍数',
  `totalBet` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '当前下注额',
  `betTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '当前下注额下连续下注次数',
  `defaultBet` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '默认下注额',
  `suggestBet` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '推荐下注额',
  `resumeBet` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '待恢复下注额',
  `avgBet` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '当前下注均值',
  `totalWin` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '当前TotalWin',
  `coinsWin` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '本次spin总赢得',
  `spinTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '累计下注次数',
  `sampleGroup` varchar(16) NOT NULL DEFAULT '' COMMENT '当前使用的样本组名称',
  `sampleCount` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '已使用样本数量',
  `jackpotAddition` varchar(255) NOT NULL DEFAULT '' COMMENT 'jackpot加成值(json)',
  `jackpotProgress` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'jackpot进度值',
  `collectNode` tinyint(2) UNSIGNED NOT NULL DEFAULT '0' COMMENT '收集进度节点',
  `collectTarget` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '收集目标值',
  `collectProgress` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '收集进度值',
  `collectSpinTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '收集期间下注次数',
  `collectBetSummary` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '收集期间下注总额',
  `collectAvgBet` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '收集期间下注均值',
  `collectValue` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '收集期间附加值',
  `featureId` varchar(16) NOT NULL DEFAULT '' COMMENT '当前feature',
  `featureNo` varchar(16) NOT NULL DEFAULT '' COMMENT '当前feature编号',
  `activated` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'feature是否已激活',
  `featureDetail` text NOT NULL COMMENT 'feature详细信息',
  `bakFeatures` text NOT NULL COMMENT 'feature备份信息',
  `stacks` varchar(128) NOT NULL DEFAULT '' COMMENT '下次spin时的stack替换元素',
  `featureTimes` varchar(255) NOT NULL DEFAULT '' COMMENT '中feature次数(json)',
  `bonusCredit` bigint(20) NOT NULL DEFAULT '0' COMMENT '下注积分',
  `suggestBetIntervene` varchar(1) NOT NULL DEFAULT '' COMMENT '推荐Bet干预状态[Y/H/L]',
  `enterTime` datetime DEFAULT NULL COMMENT '进入机台时间',
  `enterBalance` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '进入机台时的资产',
  `gameExtra` text NOT NULL COMMENT '游戏扩展信息(json)',
  `enterCost` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '进入至离开机台期间的消耗的金币数',
  `enterWin` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '进入至离开机台期间赢得的金币数',
  `enterSpinTimes` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '进入至离开机台期间下注次数',
  `lastSpinElements` text  COMMENT '最后一次的牌面信息',
  PRIMARY KEY (`uid`, `machineId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='玩家游戏信息表';

CREATE TABLE IF NOT EXISTS `t_analysis` (
  `uid` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
  `regCoins` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '注册金币数',
  `spinTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'spin总次数',
  `spinTimesToday` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '今日spin总次数',
  `freespinTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'freespin总次数',
  `freespinMaxTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'freespin最大次数',
  `winTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '中奖总次数',
  `bigWinTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '中奖总次数-bigWin',
  `bankruptTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '破产总次数',
  `greatestWin` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '最高中奖数额',
  `maxWinMultiple` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '最高中奖倍数',
  `jackpotWin` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '赢得jackpot总额',
  `jackpotTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '赢得jackpot次数',
  `totalCost` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '下注总额',
  `totalGained` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '中奖总额',
  `sampleCount` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '样本使用总个数',
  `noviceProgress` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '新手样本总进度',
  `noviceEnded` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '新手样本已结束',
  `avgBet` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '平均下注额',
  `avgBetRatio` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '平均下注后手比',
  `lowBetRatioTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '低后手比下注次数',
  `defaultBet` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '默认下注额',
  `lastSpinTime` datetime DEFAULT NULL COMMENT '上次spin时间',
  `lastBet` bigint(20) UNSIGNED DEFAULT '0' NULL COMMENT '上次bet值',
  `recentAvgBet` bigint(20) UNSIGNED DEFAULT '0' NULL COMMENT '最近的平均bet值',
  `commonUsedBet` bigint(20) UNSIGNED DEFAULT '0' NULL COMMENT '最近的常用bet值',
  `notBigWinTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '连续不中大奖次数',
  `highBetCoolingTime` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'highBet干预冷却时间',
  `noviceProtect` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '新手保护状态',
  `returnProtect` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '回归保护状态',
  `rechargeProtect` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '付费保护状态',
  `bankruptProtect` varchar(1) NOT NULL DEFAULT '' COMMENT '破产保护状态[Y/N]',
  `tooRichIntervene` varchar(1) NOT NULL DEFAULT '' COMMENT '资产过多干预状态[Y/N]',
  `isRelativeBankruptBack` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否相对破产回归',
  `lastBalances` varchar(64) NOT NULL DEFAULT '' COMMENT '最后2次资产记录',
  `balanceWaves` varchar(128) NOT NULL DEFAULT '' COMMENT '资产波动信息',
  `initBalanceToday` bigint(20) NOT NULL DEFAULT '0' COMMENT '今日初始资产(首次spin时)',
  `profitToday` bigint(20) NOT NULL DEFAULT '0' COMMENT '今日盈利',
  `reSpinFreeGameTimes` int(10) NOT NULL DEFAULT '0' COMMENT '重转 FreeGame 次数',
  `lastMachineId` int(10) NOT NULL COMMENT '最后所在机台ID',
  `activityUsedBet` bigint(20) UNSIGNED DEFAULT '0' NULL COMMENT '活动bet值',
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='游戏统计数据表';

CREATE TABLE IF NOT EXISTS `t_payment` (
  `pid` varchar(32) NOT NULL COMMENT '订单ID',
  `uid` int(10) UNSIGNED NOT NULL COMMENT '购买者ID',
  `index` varchar(8) NOT NULL COMMENT '商品索引',
  `shop` tinyint(1) NOT NULL COMMENT '商店类型',
  `itemId` varchar(8) NOT NULL COMMENT '物品ID',
  `count` bigint(20) UNSIGNED NOT NULL DEFAULT '1' COMMENT '购买数量',
  `extra` decimal(12,2) UNSIGNED DEFAULT NULL COMMENT '优惠比例',
  `price` decimal(12,2) UNSIGNED NOT NULL COMMENT '支付金额',
  `currency` varchar(8) NOT NULL DEFAULT '' COMMENT '支付币种',
  `balance` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '支付时的资产',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '订单状态 0未完成 1已完成 2已退款 3已丢单',
  `isTest` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否是测试订单',
  `orderId` varchar(255) DEFAULT NULL COMMENT '第三方订单ID',
  `payChannel` tinyint(1) DEFAULT NULL COMMENT '支付渠道',
  `payParams` text DEFAULT NULL COMMENT '支付参数',
  `createTime` datetime NOT NULL COMMENT '下单时间',
  `completeTime` datetime DEFAULT NULL COMMENT '支付完成时间',
  `refundTime` datetime DEFAULT NULL COMMENT '退款时间',
  `modifyTime` datetime DEFAULT NULL COMMENT '修改时间',
  `version` varchar(16) NOT NULL DEFAULT '' COMMENT '支付版本',
  PRIMARY KEY (`pid`),
  UNIQUE KEY `orderId` (`orderId`,`payChannel`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='订单信息表';

CREATE TABLE IF NOT EXISTS `t_recharge` (
  `uid` int(10) UNSIGNED NOT NULL COMMENT '购买者ID',
  `amount` decimal(12,2) UNSIGNED NOT NULL DEFAULT '0' COMMENT '充值总金额',
  `times` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '充值总次数',
  `coinsTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '购买金币总次数',
  `diamondTimes` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '购买钻石总次数',
  `maxAmount` decimal(12,2) NOT NULL DEFAULT '0' COMMENT '单次最大充值金额',
  `mostAmount` decimal(12,2) NOT NULL DEFAULT '0' COMMENT '充值次数最多的金额',
  `lastAmount` decimal(12,2) NOT NULL DEFAULT '0' COMMENT '最后一次充值金额',
  `lastTime` datetime DEFAULT NULL COMMENT '最后一次充值时间',
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='付费信息表';

CREATE TABLE IF NOT EXISTS `t_item` (
  `uid` int(10) unsigned NOT NULL COMMENT '用户ID',
  `itemId` varchar(8) NOT NULL COMMENT '道具ID',
  `count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '道具数量',
  PRIMARY KEY (`uid`,`itemId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='道具信息表';

CREATE TABLE IF NOT EXISTS `t_online` (
  `uid` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
  `isOnline` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '是否在线',
  `isPlaying` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否在玩',
  `onlineTime` datetime NOT NULL COMMENT '上线时间',
  `offlineTime` datetime DEFAULT NULL COMMENT '离线时间',
  `activeTime` datetime DEFAULT NULL COMMENT '最后活跃时间',
  `totalTime` int(10) DEFAULT '0' COMMENT '在线累计时长',
  `level` int(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '角色等级(冗余)',
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='玩家在线信息表';