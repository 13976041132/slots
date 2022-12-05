<?php
/**
 * 自动加载
 * 遵循PSR-4规范
 * 顶级命名空间统一为FF
 */

namespace FF\Framework\Common;

function autoload($class)
{
    $path = PATH_ROOT;
    $spaces = explode('\\', $class);
    $className = array_pop($spaces);

    if (!$spaces || $spaces[0] != 'FF') return;

    array_shift($spaces);

    //例外-允许框架文件置于其他目录
    if ($spaces && $spaces[0] == 'Framework') {
        if (PATH_FWK != PATH_ROOT . '/Framework') {
            array_shift($spaces);
            $path = PATH_FWK;
        }
    }

    if ($spaces) {
        $path .= '/' . implode('/', $spaces);
    }

    file_include($path . '/' . $className . '.php');
}

spl_autoload_register('FF\\Framework\\Common\\autoload');