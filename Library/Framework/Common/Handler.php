<?php
/**
 * 系统错误/异常处理
 */

namespace FF\Framework\Common;

use FF\Framework\Core\FF;

function _error_handler($err_no, $err_str, $err_file, $err_line)
{
    if (($err_no & error_reporting()) !== $err_no) {
        return;
    }

    FF::onError(Code::SYSTEM_ERROR, $err_str, $err_file, $err_line);
}

/**
 * @param $e \Exception | \Error
 */
function _exception_handler($e)
{
    $code = $e->getCode() ? $e->getCode() : Code::SYSTEM_ERROR;

    FF::onError($code, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTrace());
}

function _shutdown_handler()
{
    $error = error_get_last();

    if (isset($error['type']) && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING))) {
        _error_handler($error['type'], $error['message'], $error['file'], $error['line']);
    }
}