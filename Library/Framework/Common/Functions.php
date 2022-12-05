<?php
/**
 * 常用方法
 */

function uint($num)
{
    return max(0, (int)$num);
}

function now()
{
    return date('Y-m-d H:i:s');
}

function today()
{
    return date('Y-m-d');
}

function is_today($time)
{
    return date('Ymd', $time) == date('Ymd');
}

function yesterday()
{
    return date('Y-m-d', time() - 86400);
}

function getDateByDiff($diff, $format = 'Y-m-d')
{
    return date($format, strtotime('+' . $diff . ' days', time()));
}

function is_empty($var)
{
    return empty($var) && !is_numeric($var);
}

function in_range($val, $rect, $left_eq = true, $right_eq = true)
{
    if (!is_array($rect)) return $val == $rect;
    if (!isset($rect[0]) || !isset($rect[1])) return false;

    if ($left_eq && $val < $rect[0]) return false;
    if (!$left_eq && $val <= $rect[0]) return false;

    if ($right_eq && $rect[1] && $val > $rect[1]) return false;
    if (!$right_eq && $rect[1] && $val >= $rect[1]) return false;

    return true;
}

function get_ip()
{
    $ip = '';
    $unknown = 'unknown';

    if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]) && strcasecmp($_SERVER["HTTP_X_FORWARDED_FOR"], $unknown)) {
        $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    } elseif (!empty($_SERVER["REMOTE_ADDR"]) && strcasecmp($_SERVER["REMOTE_ADDR"], $unknown)) {
        $ip = $_SERVER["REMOTE_ADDR"];
    }

    if (false !== strpos($ip, ",")) {
        $ip = explode(",", $ip)[0];
    }

    return $ip;
}

function get_host_url()
{
    if (is_cli()) return '';

    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?: $_SERVER['REQUEST_SCHEME'];

    $url = $proto . '://' . $_SERVER['HTTP_HOST'];

    return $url;
}

//cli模式下
function get_host_ip()
{
    exec("ifconfig | sed -n '/inet /p' | awk '{print $2}'", $addres);
    return $addres;
}

function get_cli_args()
{
    $args = array();

    if (!isset($GLOBALS['argv'])) return $args;
    if (count($GLOBALS['argv']) == 1) return $args;

    foreach ($GLOBALS['argv'] as $k => $arg) {
        if ($k == 0) continue;
        $arg = explode('=', $arg);
        if (count($arg) != 2) continue;
        $args[$arg[0]] = $arg[1];
    }

    return $args;
}

function is_ajax()
{
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        return $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
    } else {
        return false;
    }
}

function dp3p()
{
    header("P3P:CP='ALL DSP CURa ADMa DEVa CONi OUT DELa IND PHY ONL PUR COM NAV DEM CNT STA PRE'");
}

function _microtime()
{
    return (double)(microtime(true) * 1000);
}

function is_cli()
{
    return php_sapi_name() === 'cli';
}

//是否是跑测试 或 跑样本库
function is_virtual()
{
    return defined('TEST_ID');
}

//输出信息
function cli_output($message)
{
    if (!is_cli()) return;

    $message = is_scalar($message) ? $message : json_encode($message);

    echo '[' . now() . '] ' . $message . PHP_EOL;
}

//退出样本库
function cli_exit($message = '')
{
    if ($message) cli_output($message);
    cli_output('Exit');
    exit(0);
}

function file_require($file, $once = false)
{
    if (!file_exists($file)) {
        throw new \Exception("File {$file} isn't exits!", \FF\Framework\Common\Code::FILE_NOT_EXIST);
    } else {
        return $once ? require_once($file) : require($file);
    }
}

function file_include($file, &$exists = false)
{
    $exists = file_exists($file);

    return $exists ? include($file) : null;
}

function exec_command(&$cmd, $async = false, $log_file = '')
{
    if (IS_WIN && $async) {
        $cmd .= ' > ' . ($log_file ?: 'nul');
        pclose(popen("start /B " . $cmd, "r"));
    } else {
        $cmd .= ' > ' . ($log_file ?: '/dev/null');
        if ($async) $cmd .= " &";
        exec($cmd);
    }
}

function exec_php_file($file, $args = null, $async = false, $log_file = '')
{
    $cmd = "php {$file}";

    if (!$args || !is_array($args)) {
        $args = array();
    }
    $args['env'] = ENV;
    foreach ($args as $k => $v) {
        $cmd .= " {$k}={$v}";
    }

    exec_command($cmd, $async, $log_file);

    return $cmd;
}

function redirect($url)
{
    header('location: ' . $url);
    exit(0);
}

function array_last($array)
{
    if (!$array) return null;

    $keys = array_keys($array);
    $lastKey = $keys[count($keys) - 1];

    return $array[$lastKey];
}

function array_recombine($data, $keys)
{
    $result = array();

    foreach ($keys as $key) {
        $result[$key] = isset($data[$key]) ? $data[$key] : null;
    }

    return $result;
}

function array_deep_diff($array1, $array2)
{
    $diff = array();

    foreach ($array1 as $key => $val) {
        if (!key_exists($key, $array2)) {
            $diff[$key] = $val;
        } elseif (is_array($val)) {
            if (!is_array($array2[$key])) {
                $diff[$key] = $val;
            } else {
                $_diff = array_deep_diff($val, $array2[$key]);
                if ($_diff) {
                    $diff[$key] = $_diff;
                }
            }
        } elseif ($val !== $array2[$key]) {
            $diff[$key] = $val;
        }
    }

    return $diff;
}

function zip_get_files($zip_file)
{
    if (!$zip = zip_open($zip_file)) return false;

    $files = array();

    while ($zip_entry = zip_read($zip)) {
        $files[] = zip_entry_name($zip_entry);
    }

    zip_close($zip);

    return $files;
}

function zip_read_file($zip_file, $file)
{
    if (!$zip = zip_open($zip_file)) return false;

    $content = '';
    if (substr($file, 0, 1) == '/') {
        $file = substr($file, 1);
    }

    while ($zip_entry = zip_read($zip)) {
        $entry_name = zip_entry_name($zip_entry);
        if ($entry_name == $file) {
            if (zip_entry_open($zip, $zip_entry)) {
                while ($str = zip_entry_read($zip_entry)) {
                    $content .= $str;
                }
                zip_entry_close($zip_entry);
            }
            break;
        }
    }

    zip_close($zip);

    return $content;
}

function unzip($zip_file, $dist_dir = null)
{
    $zip = zip_open($zip_file);
    if (!$zip) return false;

    if (!$dist_dir) {
        $dist_dir = substr($zip_file, 0, strrpos($zip_file, '.'));
    }

    !is_dir($dist_dir) && mkdir($dist_dir, 0777, true);

    $files = array();

    while ($zip_entry = zip_read($zip)) {
        $entry_name = zip_entry_name($zip_entry);
        if (substr($entry_name, -1) == '/') {
            if ($entry_name !== '/') {
                $dir = $dist_dir . '/' . $entry_name;
                !is_dir($dir) && mkdir($dir, 0777, true);
            }
            continue;
        }
        if (zip_entry_open($zip, $zip_entry, "r")) {
            $file_name = $dist_dir . '/' . $entry_name;
            $file_size = zip_entry_filesize($zip_entry);
            $stream = zip_entry_read($zip_entry, $file_size);
            file_put_contents($file_name, $stream);
            zip_entry_close($zip_entry);
            $files[] = $entry_name;
        }
    }

    zip_close($zip);

    return $files;
}

/**
 * 目录文件扫描
 * @param string $root_dir 要扫描的跟目录
 * @param string $sub_dir 要扫描的子目录
 * @param string $file_only 是否只返回文件
 * @param string $relative 是否只返回相对路径
 */
function dir_scan($root_dir, $file_only = false, $relative = true, $sub_dir = '')
{
    $dir = $root_dir;
    if (!is_empty($sub_dir)) {
        $dir .= '/' . $sub_dir;
    }
    if (!is_dir($dir)) {
        return false;
    }

    $files = [];
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if ($sub_dir) {
            $item = $sub_dir . '/' . $item;
        }
        $return_item = $item;
        if (!$relative) {
            $return_item = $root_dir . '/' . $return_item;
        }
        if (is_dir($root_dir . '/' . $item)) {
            if (!$file_only) {
                $files[] = $return_item;
            }
            $sub_files = dir_scan($root_dir, $file_only, $relative, $item);
            $files = array_merge($files, $sub_files);
        } else {
            $files[] = $return_item;
        }
    }

    return $files;
}

/**
 * 删除目录
 * 可递归删除目录内文件及子目录
 * @param $dir
 * @param $remove_self
 * @param $excludeItems
 */
function dir_remove($dir, $remove_self = true, $excludeItems = array())
{
    if (!is_dir($dir)) return;

    $items = dir_scan($dir, false, true);
    if (!$items) {
        if ($remove_self) {
            rmdir($dir);
        }
        return;
    }
    foreach ($items as $item) {
        if (in_array($item, $excludeItems)){
            continue;
        }
        if (is_dir($item)) {
            dir_remove($dir . '/' . $item);
        } else {
            unlink($dir . '/' . $item);
        }
    }

    if ($remove_self) {
        rmdir($dir);
    }
}

