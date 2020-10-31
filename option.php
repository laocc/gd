<?php


/**
 * 这只是配置示例，需将此文件复制到实际项目中
 */

$conf = [];

//host
$conf['host'] = 'http://pic.521wxs.cn';

//文件保存位置
$conf['root'] = _ROOT . '/upload';

//生成路径的规则
$conf['src'] = '{img}_{width}{mode}{height}.{ext}#{rand}';

//上面规匹配的正则表达式，必须用子组命名的方式
$conf['pattern'] = '/\/(?<img>.+)\_(?<width>\d+)(?<mode>[xvz])(?<height>\d+)\.(?<ext>jpg|gif|png|bmp|jpeg)/i';

//尺寸预定义，只能是10以下，10以上的都视为直接指定尺寸
$conf['size'][0] = 60;
$conf['size'][1] = 100;
$conf['size'][2] = 128;
$conf['size'][3] = 180;
$conf['size'][4] = 414;
$conf['size'][5] = [414, 300];

return $conf;