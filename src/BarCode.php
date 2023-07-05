<?php

namespace esp\gd;

use esp\error\Error;
use esp\gd\bar\BCG_code128;
use esp\gd\bar\BCG_Color;
use esp\gd\bar\BCG_FontFile;
use esp\gd\bar\BCG_FontPhp;

/**
 * 条形码
 *
 * Class BarCode
 *
 * $code = Array();
 * $code['value'] = $val;        //条码内容
 * $code['font'] = _ROOT . 'font/arial.ttf';//字体，若不指定，则用PHP默认字体
 * $code['size'] = 20;         //字体大小
 * $code['label'] = true;      //是否需要条码下面标签
 * $code['pixel'] = 5;         //分辨率即每个点显示的像素，建议3-5
 * $code['height'] = 20;       //条码部分高，实际像素为此值乘pixel
 * $code['style'] = null;      //条码格式，可选：A,B,C,或null，若为null则等同于C
 * Code1::create($code);
 */
class BarCode extends BaseGD
{
    /**
     * @param $option
     * @return array|null[]
     * @throws Error
     */
    public function create($option)
    {
        if (!is_array($option)) {
            $option = ['value' => $option];
        }
        $code = array();
        $code['code'] = microtime(true);        //条码内容
        $code['font'] = null;       //字体，若不指定，则用PHP默认字体
        $code['size'] = 10;         //字体大小
        $code['split'] = 4;         //条码值分组，每组字符个数，=0不分，=null不显示条码值
        $code['pixel'] = 3;         //分辨率即每个点显示的像素，建议3-5
        $code['height'] = 20;       //条码部分高，实际像素为此值乘pixel
        $code['style'] = null;      //条码格式，可选：A,B,C,或null，若为null则等同于C，这基本不需要指定，非C的条码，还不知道用在什么地方
        $code['root'] = getcwd();    //保存文件目录，不含在URL中部分
        $code['path'] = 'code1/';   //含在URL部分
        $code['save'] = 0;          //0：只显示，1：只保存，2：即显示也保存
        $code['filename'] = null;      //不带此参，或此参为false值，则随机产生

        $option += $code;

        $option['code'] = strval($option['code']);
        $option['root'] = rtrim($option['root'], '/');
        $option['path'] = '/' . trim($option['path'], '/') . '/';

        if (!preg_match('/^[\x20\w\!\@\#\$\%\^\&\*\(\)\_\+\`\-\=\[\]\{\}\;\'\\\:\"\|\,\.\/\<\>\?]+$/', $option['code'])) {
            throw new Error("条形码只能是英文、数字及半角符号组成");
        }

        if (!!$option['split']) {
            $option['label'] = '* ' . implode(' ', str_split($option['code'], intval($option['split']))) . ' *';
        } elseif ($option['split'] === null) {
            $option['label'] = null;
        } else {
            $option['label'] = $option['code'];
        }

        $font = (!!$option['font']) ?
            (new BCG_FontFile($option['font'], intval($option['size']))) :
            (new BCG_FontPhp($option['size']));

        $color = new BCG_Color(0, 0, 0);
        $background = new BCG_Color(255, 255, 255);

        $file = $this->getFileName($option['save'], $option['root'], $option['path'], $option['filename'], 'png');

        $Obj = new BCG_code128();
        $Obj->setLabel($option['label']);
        $Obj->setStart($option['style']);
        $Obj->setThickness($option['height']);
        $Obj->setScale($option['pixel']);
        $Obj->setBackgroundColor($background);
        $Obj->setForegroundColor($color);
        $Obj->setFont($font);
        $Obj->parse($option['code']);

        $size = $Obj->getDimension(0, 0);
        $width = max(1, $size[0]);
        $height = max(1, $size[1]);
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $background->allocate($im));
        $Obj->draw($im);

        $this->display = $option["save"];
        $this->draw($im, IMAGETYPE_PNG, $file['filename']);
        return $file;
    }
}



