<?php
/**
 * 常量定义
 */

const APP_ID_ANDROID = 0;
const APP_ID_IOS = 1;
const APP_ID_AMAZON = 2;

const TCP_PROTOCOL_VER = 1;

const DB_MAIN = 'main';
const DB_ADMIN = 'admin';
const DB_ANALYSIS = 'analysis';
const DB_CONFIG = 'config';
const DB_LOG = 'log';
const DB_TEST = 'test';

const ITEM_COINS = 'I101';
const ITEM_GEM = 'I201';
const ITEM_EXP = 'I301';
const ITEM_VIP_PTS = 'I401';
const ITEM_SPIN_PTS = 'I501';
const ITEM_FREE_SPIN = 'I601';
const ITEM_FEATURE = 'I701';
const ITEM_WHEEL = 'I801';
const ITEM_JACKPOT = 'I901';

const FEATURE_FREE_SPIN = 'FreeSpin';
const FEATURE_WILD_MULTI = 'Wild Multi';
const FEATURE_LIGHTNING = 'Lightning';
const FEATURE_HOLD_AND_SPIN = 'HoldSpin';
const FEATURE_COLLECT_GAME = 'Collect';
const FEATURE_PICK_GAME = 'PickGame';

const EVENT_LOGIN = 'Login';
const EVENT_LOGS = 'Logs';
const EVENT_PING = 'ping';
const EVENT_FLUSH_LOGS = 'FlushLogs';
const EVENT_SETTLEMENT = 'Settlement';
const EVENT_USER_STATUS = 'UserStatus';
const VIP_MIN_LEVEL = 1;

if (!defined('SWOOLE_SOCK_SYNC')) {
    define('SWOOLE_SOCK_SYNC', '');
}
if (!defined('SWOOLE_SOCK_ASYNC')) {
    define('SWOOLE_SOCK_ASYNC', '1');
}

class AdminOpCategory
{
    const CONFIG = 1;
    const ACCESS = 2;

    public static function getAll()
    {
        return array(
            self::CONFIG => '配置信息',
            self::ACCESS => '访问记录'
        );
    }
}