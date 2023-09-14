<?php

namespace esp\gd;

use esp\gd\qr\qr_Encode;

/**
 *
 * 生成二维码
 * 1，可以指定二维码颜色；
 * 2，可以用图片当成二维码主体色；
 * 3，可以加LOGO在二维码中间；
 * 4，可以指定背景色；
 * 5，也可以将有关数据组成一个三维数组，供其他语言以像素方式显式，只需传入文本，前几种功能均无效。
 *
 * "<img src='data:image/png;base64,{$data}'>";
 *
 * $v = \tools\Code2::create($option);
 * 可选参数见函数前面定义，以下几点须注意：
 *
 * $option['level']代表该二维码容错率，
 *      可选：0123，或LMQH分别对应0123
 *      控制：二维码的容错率，分别为：7，15，25，30
 *      其中：当=0时，不可以加LOGO
 *
 * $option['save']代表当前操作是将二维码保存到文件，=false直接显示，=true返回下列数据
 * Array
 * (
 * [root] => /home/web/blog/code/       保存目录，一般不用在URL中
 * [path] => qrCode/                    目录中的文件夹名，用在URL中
 * [name] => 6dc84ecc2ae4a614e6707c0cb3b988c7.png
 * )
 * 最终URL：http://www.domain.com/qrCode/6dc84ecc2ae4a614e6707c0cb3b988c7.png
 * URL须自行组合。
 *
 *
 * $option['color']表示二维码颜色，也可以指定为一个实际存在的图片
 *      注意：若用图片，则该图片大部分应该是以深色为主，否则生成的二维码可能很难识别
 *
 * $option['background']表示二维码背景色，不要太深了
 *
 * $option['logo']如果想在二维码中间加个LOGO，就用它指定一个实际存在的图片
 *      注意：这个图片最好是正方形，否则从左上角按最小边裁切出一个正方形
 *
 * $option['parent']将二维码贴在这个图片指定x,y位置，若不指定位置，则居中
 * $option['shadow']如果有底图，则这个可以定义一个阴影，可以指定偏移量、颜色、透明度
 *
 * TODO:特效加的越多，越耗时。
 *
 */
class QrCode extends BaseGD
{
    public function create(array $dimOption)
    {
        $option = array();
        $option['text'] = 'no Value';
        $option['level'] = 'Q';    //可选LMQH
        $option['size'] = 10;    //每条线像素点,一般不需要动，若要固定尺寸，用width限制
        $option['margin'] = 1;    //二维码外框空白，指1个size单位，不是指像素
        $option['width'] = 0;     //生成的二维码宽高，若不指定则以像素点计算
        $option['color'] = '#000000';   //二维码本色，也可以是图片
        $option['background'] = '#ffffff';  //二维码背景色
        $option['root'] = getcwd();  //保存目录
        $option['path'] = 'code2/';        //目录里的文件夹
        $option['filename'] = '';        //生成的文件名

        $option['logo'] = null;         //LOGO图片
        $option['logo_border'] = '#ffffff';  //LOGO外边框颜色

        $option['parent'] = null;//一个文件地址，将二维码贴在这个图片上
        $option['parent_x'] = null;//若指定，则以指定为准
        $option['parent_y'] = null;//为null时，居中

        $option['shadow'] = null;//颜色色值，阴影颜色，只有当parent存在时有效
        $option['shadow_x'] = 2;//阴影向右偏移，若为负数则向左
        $option['shadow_y'] = 2;//阴影向下偏移，若为负数则向上
        $option['shadow_alpha'] = 0;//透明度，百分数

        $option = $dimOption + $option;
        if (isset($option['display'])) $this->display = intval($option['display']);
        else if (isset($option['save'])) $this->display = intval($option['save']);

        if (isset($option['root'])) $this->root = rtrim($option['root'], '/');
        if (isset($option['path'])) $this->path = '/' . trim($option['path'], '/');

        $option['width'] = is_int($option['width']) ? $option['width'] : 400;
        $option['size'] = is_int($option['size']) ? (($option['size'] < 1 or $option['size'] > 20) ? 10 : $option['size']) : 10;
        $option['margin'] = is_int($option['margin']) ? (($option['margin'] < 0 or $option['margin'] > 20) ? 1 : $option['margin']) : 1;

        if (is_array($option['text'])) $option['text'] = json_encode($option['text'], 256 | 64);
        if (strlen($option['text']) < 1) $option['text'] = 'null';
        if (strlen($option['text']) > 500) $option['text'] = substr($option['text'], 0, 500);

        if (is_int($option['level']) and $option['level'] > 3) $option['level'] = 3;
        $option['level'] = preg_match('/^[lQmh0123]$/i', $option['level']) ? strtoupper($option['level']) : 'Q';
        $level = ['L' => 0, 'M' => 1, 'Q' => 2, 'H' => 3];
        if (in_array($option['level'], ['L', 'M', 'Q', 'H'])) $option['level'] = $level[$option['level']];

        $file = $this->getFileName($this->root, $this->path, $option['filename'], 'png');

        $ec = new qr_Encode();
        $im = $ec->create($option);

        $option = [
            'save' => $option['save'],//0只显示，1：显示，2：保存，3即显示也保存，4：返回GD数据流
            'filename' => $file['filename'] ?? null,
            'type' => IMAGETYPE_PNG,//文件类型
        ];
        if ($option['save'] & 8) return $im;

        $gd = $this->draw($im, IMAGETYPE_PNG, $option['filename']);
        if ($option['save'] & 4) return $gd;
        if ($option['save'] & 1) exit;
        return $file;
    }

    /**
     * 计算要组成二维码的内容为一个三维数组，供JS调用
     * @param $text
     * @return array
     */
    public static function bin($text)
    {
        if (is_array($text)) $text = $text['text'];
        $obj = new qr_Encode();
        $val = $obj->encode($text, 1);
        $jsVal = array();
        foreach ($val as $i => &$a) {
            $jsVal[$i] = str_split($a);
        }
        return $jsVal;
    }

}







