<?php
/**
 * 常量定义
 */

namespace FF\Framework\Common;

class Env
{
    const DEVELOPMENT = 'develop';
    const TESTING = 'test';
    const PREVIEW = 'preview';
    const PRODUCTION = 'product';
}

class DBResult
{
    const AFFECTED_ROWS = 1; //影响行数
    const FETCH_ONE = 2; //单行记录
    const FETCH_ALL = 3; //多行记录
    const STATEMENT = 4; //原始结果集
}

class Format
{
    const TEXT = 'text';
    const HTML = 'html';
    const JSON = 'json';
    const PBUF = 'pbuf';
    const XML = 'xml';
}

class Code
{
    const SUCCESS = 0;
    const FAILED = 1;
    const SYSTEM_ERROR = 2;
    const SYSTEM_TIMEOUT = 3;
    const SYSTEM_BUSY = 4;
    const ENV_INIT_FAILED = 5;
    const CONFIG_MISSED = 6;
    const ROUTE_INVALID = 7;

    const FILE_NOT_EXIST = 11;
    const CLASS_NOT_EXIST = 12;
    const CONTROLLER_NOT_EXIST = 13;
    const METHOD_NOT_EXIST = 14;
    const PARAMS_MISSED = 15;
    const PARAMS_INVALID = 16;

    const DB_CONNECT_FAILED = 21;
    const DB_EXECUTE_FAILED = 22;
    const REDIS_CONNECT_FAILED = 23;
    const REDIS_AUTH_FAILED = 24;
}