<?php

namespace esp\gd\gds;

use esp\gd\gd\Image;

class Mark
{

    /**
     * 给图片加水印
     *
     * @param $picFile
     * @param array $config
     * @return bool|string
     */
    public static function mark($picFile, array $config)
    {
        $config += ['backup' => true, 'order' => 0, 'img' => ['file' => null], 'txt' => ['text' => null],];
        if (!is_file($picFile) or (!$config['img']['file'] and !$config['txt']['text']))
            return '原文件不存在，或即无水印图片也无水印文字';

        $img = array();
        if (!!$config['img']['file']) {
            $_img_set = $config['img'] + [
                    'color' => '',//要抽取的水印背景色
                    'alpha' => 100,
                    'position' => 0,//位置，按九宫位
                    'offset' => [0, 0],
                ];
            if (!is_array($_img_set['offset'])) $_img_set['offset'] = json_decode($_img_set['offset'], true);

            $img['file'] = $_img_set['file'];        //水印文件名
            $img['color'] = $_img_set['color'];        //'#000000';		//要抽除的颜色
            $img['alpha'] = $_img_set['alpha'];                //透明度,数越小,越透,最大100
            $img['position'] = $_img_set['position'];
            $img['border'] = 1;                        //消除边框像素
            $img['x'] = $_img_set['offset'][0];                            //水印偏移量,正负
            $img['y'] = $_img_set['offset'][1];

            if (isset($config['fix'])) {
                if (is_array($config['fix'])) {
                    $img['position'] = $config['fix'];
                } else {
                    $img['position'] = json_decode($config['fix'], true);
                }
            } else {
                $img['position'] = json_decode($img['position'], true);
                if (is_array($img['position'])) $img['position'] = $img['position'][array_rand($img['position'])];
            }
        }

        $txt = array();
        if (!!$config['txt']['text']) {
            $_txt_set = $config['txt'] + [
                    'size' => 30,
                    'color' => '#eeeeee',
                    'alpha' => 75,
                    'position' => 0,//位置，按九宫位
                    'shade' => [0, 0],
                    'shade_color' => '#555555',
                    'offset' => [0, 0],
                    'font' => _FONT_ROOT . '/fonts/simkai.ttf',
                ];
            if (!is_array($_txt_set['offset'])) $_txt_set['offset'] = json_decode($_txt_set['offset'], true);
            if (!is_array($_txt_set['shade'])) $_txt_set['shade'] = json_decode($_txt_set['shade'], true);

            $txt['text'] = $_txt_set['text'];
            $txt['utf8'] = 1;                            //源文字是否UTF8格式,若是从UTF8数据库取出的,填1,手工填的文字,就填0
            $txt['font'] = $_txt_set['font'];        //水印字体水印文字simkai.ttf
            $txt['size'] = $_txt_set['size'];        //字体大小,1-5
            $txt['color'] = $_txt_set['color'];        //字体颜色
            $txt['alpha'] = $_txt_set['alpha'];        //透明度,数超小,越透,最大100
            $txt['position'] = $_txt_set['position'];
            $txt['x'] = $_txt_set['offset'][0];                    //水印偏移量,正负
            $txt['y'] = $_txt_set['offset'][1];
            $txt['a'] = $_txt_set['shade'][0];                    //文字阴影,正数为向右
            $txt['b'] = $_txt_set['shade'][1];                    //正数为向下
            $txt['shade'] = $_txt_set['shade_color'];        //阴影色
            $txt['point'] = 2500;                //自动调整水印文字大小时,根据主图大小的比例,
            $txt['angle'] = 0;            //角度,暂时角度只能为0
            $txt['expand'] = 1;            //扩张像素,因中文显示时,比实际计算的尺寸要大,会造成有些边显示不了,建议为size的1/5左右,

            if (isset($txt['fix'])) {
                $txt['position'] = json_decode($txt['fix'], true);
            } else {
                $txt['position'] = json_decode($txt['position'], true);
                if (is_array($txt['position'])) $txt['position'] = $txt['position'][array_rand($txt['position'])];
            }
        }

        if ($config['backup']) Gd::backup($picFile);

        return Image::Mark_Create($picFile, $img, $txt, $config['order'], ['save' => 1]);
    }


}