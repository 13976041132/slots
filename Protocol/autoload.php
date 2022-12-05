<?php
/**
 * Google ProtoBuf Autoloader
 * @param string $class
 */
function pb_autoload($class)
{
    $spaces = explode('\\', $class);
    $className = array_pop($spaces);

    if (!$spaces) return;

    $space = implode('\\', $spaces);

    if (strpos($space, 'Google\\Protobuf') !== false) {
        $path = PATH_LIB . '/Vendor/ProtoBuf';
    } elseif (in_array($spaces[0], ['GPBClass', 'GPBMetadata'], true)) {
        $path = PATH_ROOT . '/Protocol';
    } else {
        return;
    }

    $path = $path . '/' . str_replace('\\', '/', $space);

    file_include($path . '/' . $className . '.php');
}

spl_autoload_register('pb_autoload');