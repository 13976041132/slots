<?php
/**
 * 日志管理器
 */

namespace FF\Framework\Utils;

use FF\Framework\Core\FF;

class Log
{
    const ERROR = 0;
    const WARNING = 1;
    const INFO = 2;
    const DEBUG = 3;

    private static $level = 2;
    private static $path = '';
    private static $format = '%d [%t] [%a] [%u] %m';

    private static $streams = [];

    public static function setOption($option)
    {
        if (!is_array($option)) return;

        isset($option['level']) && (self::$level = (int)$option['level']);
        isset($option['path']) && (self::$path = (string)$option['path']);
        isset($option['format']) && (self::$format = (string)$option['format']);
    }

    public static function debug($data, $file = 'debug.log', $path = '')
    {
        if (self::$level < self::DEBUG) return;

        self::writeLog('DEBUG', $data, $file, $path);
    }

    public static function info($data, $file = 'info.log', $path = '')
    {
        if (self::$level < self::INFO) return;

        self::writeLog('INFO', $data, $file, $path);
    }

    public static function warning($data, $file = 'warning.log', $path = '')
    {
        if (self::$level < self::WARNING) return;

        self::writeLog('WARNING', $data, $file, $path);
    }

    public static function error($data, $file = 'error.log', $path = '')
    {
        if (self::$level < self::ERROR) return;

        self::writeLog('ERROR', $data, $file, $path);
    }

    private static function writeLog($tag, $data, $file, $path = '')
    {
        if (!$path && !self::$path) return;

        if (is_cli()) {
            $uri = $GLOBALS['argv'][0];
        } elseif (FF::getRouter()->isValid()) {
            $uri = FF::getRouter()->getRoute();
        } else {
            $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
        }
        $ip = is_cli() ? '127.0.0.1' : get_ip();
        $msg = is_scalar($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);

        $find = array('%d', '%t', '%a', '%u', '%m');
        $replace = array(now(), $tag, $ip, $uri, $msg);
        $log = str_replace($find, $replace, self::$format);

        if (!$path) {
            $path = self::$path . '/' . date('Ymd');
        }
        if ((!is_dir($path) && !@mkdir($path, 0777, true))) return;

        $filePath = $path . '/' . $file;

        self::writeInFile2($file, $filePath, $log, $replace[0]);
        // self::writeInFile1($filePath, $log);
    }

    private static function writeInFile1($filePath, $log)
    {
        file_put_contents($filePath, $log . "\n", FILE_APPEND | LOCK_EX);
    }

    private static function writeInFile2($file, $filePath, $log, $logTime)
    {
        // 重置日志文件
        if (isset(self::$streams[$file]) && is_resource(self::$streams[$file]['stream']) && self::$streams[$file]['rotationTime'] < $logTime) {
            fclose(self::$streams[$file]['stream']);
        }

        // 检查文件存在
        clearstatcache();
        set_error_handler(array('FF\Framework\Utils\Log', 'customErrorHandler'));
        if (!is_file($filePath) && isset(self::$streams[$file]) && is_resource(self::$streams[$file]['stream'])) {
            fclose(self::$streams[$file]['stream']);
        }

        // 检查并打开资源
        if (!self::$streams || !isset(self::$streams[$file]) || !is_resource(self::$streams[$file]['stream'])) {
            self::$streams[$file]['stream'] = fopen($filePath, 'a');
            self::$streams[$file]['rotationTime'] = new \DateTime('tomorrow');
        }
        restore_error_handler();
        if (!is_resource(self::$streams[$file]['stream'])) {
            return;
        }

        // 写日志,锁步骤可以继续优化
        flock(self::$streams[$file]['stream'], LOCK_EX);
        fwrite(self::$streams[$file]['stream'], (string) $log. "\n");
        flock(self::$streams[$file]['stream'], LOCK_UN);
    }

    // 异常处理，直接写入 error 日志
    private static function customErrorHandler($code, $errorMsg)
    {
        if (is_cli()) {
            $uri = $GLOBALS['argv'][0];
        } elseif (FF::getRouter()->isValid()) {
            $uri = FF::getRouter()->getRoute();
        } else {
            $uri = explode('?', $_SERVER['REQUEST_URI'])[0];
        }
        $ip = is_cli() ? '127.0.0.1' : get_ip();

        $find = array('%d', '%t', '%a', '%u', '%m');
        $replace = array(now(), 'ERROR', $ip, $uri, $errorMsg);
        $log = str_replace($find, $replace, self::$format);

        $path = self::$path . '/' . date('Ymd');
        if ((!is_dir($path) && !@mkdir($path, 0777, true))) return;

        $filePath = $path . '/' . 'errorLogs.log';
        self::writeInFile1($filePath, $log);
    }
}