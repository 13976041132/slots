<?php
/**
 * http验证
 */

$authorized = false;

if (isset($_SERVER['PHP_AUTH_USER'])) {
    $user = $_SERVER['PHP_AUTH_USER'];
    $pass = $_SERVER['PHP_AUTH_PW'];
    $auth = \FF\Framework\Utils\Config::get('servers', 'WebServer/auth');
    if (!$auth || ($user === $auth['user'] && $pass === $auth['pass'])) {
        $authorized = true;
    }
}

if (!$authorized) {
    header('WWW-Authenticate: Basic realm=WebAuth');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Unauthorized';
    exit;
}