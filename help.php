<?php


use esp\gd\Thumbnail;


/*
 * 将下面函数复制到实际应用中，并指定option
 */
function thumbnail(string $img, int $size, string $rand = '')
{
    if (empty($img)) return '';
    if ($img[0] !== '/') return $img;

    $conf = include(_ROOT . '/public/upload/option.php');

    return $conf['host'] . Thumbnail::src($conf, $img, $size, $rand);
}
