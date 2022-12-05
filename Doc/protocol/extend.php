<?php
/**
 * 修改生成的proto类文件
 */

$file = __DIR__ . '/php/GPBClass/Enum/RET.php';

$content = file_get_contents($file);

$content = str_replace('class RET', 'class RET extends \FF\Framework\Common\Code', $content);

file_put_contents($file, $content);