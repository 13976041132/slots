SET NAMES utf8;

-- 数据库
CREATE DATABASE IF NOT EXISTS slots_admin DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

use slots_admin;

CREATE TABLE IF NOT EXISTS `t_admin` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account` varchar(16) NOT NULL,
  `password` varchar(32) NOT NULL,
  `realname` varchar(8) NOT NULL,
  `department` varchar(32) NOT NULL DEFAULT '',
  `post` varchar(32) NOT NULL DEFAULT '',
  `mobile` varchar(16) NOT NULL DEFAULT '',
  `email` varchar(32) NOT NULL DEFAULT '',
  `lastLoginIp` varchar(16) DEFAULT NULL,
  `lastLoginTime` datetime DEFAULT NULL,
  `createBy` int(10) UNSIGNED NOT NULL,
  `createTime` datetime NOT NULL,
  `status` tinyint(1) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account` (`account`),
  KEY `realname` (`realname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO t_admin SET account = 'root', password = MD5('123456'), realname = 'Root', createBy = 0, createTime = NOW(), status = 1;

CREATE TABLE IF NOT EXISTS `t_perm_group` (
  `id` varchar(32) NOT NULL,
  `name` varchar(32) NOT NULL,
  `createTime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY createTime (`createTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `t_perm_item` (
  `id` varchar(32) NOT NULL,
  `groupId` varchar(32) NOT NULL,
  `name` varchar(32) NOT NULL,
  `createTime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY createTime (`createTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `t_perm_role` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `t_perm_role_bind` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `aid` int(10) UNSIGNED NOT NULL,
  `roleId` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `aid_roleId` (`aid`,`roleId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `t_perm_role_item` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `roleId` int(10) UNSIGNED NOT NULL,
  `itemId` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_item` (`roleId`,`itemId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `t_operation_target` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(32) NOT NULL COMMENT '操作类别',
  `target` varchar(64) NOT NULL COMMENT '操作对象',
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_target` (`category`, `target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='操作对象列表';