<?php

namespace esp\gd;

class Text extends BaseGD
{
    const Quality = 80;    //JPG默认质量，对所有方法都有效

    /**
     * 写文字到图片，这不是写文字水印，是直接将几个字生成一个图片
     *
     * @param array $text
     * @param array $option
     * @return array|false|resource|null
     */
    public function create(array $text, array $option = [])
    {
        $option += [
            'width' => 0,
            'height' => 0,
            'quality' => self::Quality,
            'background' => '#ffffff',
            'alpha' => 0,//透明度
            'save' => 0,//0：只显示，1：只保存，2：即显示也保存
            'root' => _RUNTIME . '/code/',
            'path' => 'text/',
        ];
        $bTxt = [
            'size' => 20,
            'x' => null,//xy指文字左下角位置
            'y' => null,
            'font' => null,
            'color' => '#000000',
            'angle' => 0,//每个字的角度
            'vertical' => false,//竖向
            'block' => false,//整快输出
            'percent' => 1,//字体间距与字号比例
        ];

        if ($option['background'][0] === '/') {
            $info = getimagesize($option['background']);
            $im = $this->createIM($option['background'], $info[2]);
        } else {
            $im = imagecreatetruecolor($option['width'], $option['height']);//建立一个画板
            $bg = $this->createColor($im, $option['background'], $option['alpha']);//拾取一个完全透明的颜色
            imagefill($im, 0, 0, $bg);
            imagealphablending($im, true);
            imagesavealpha($im, true);//设置保存PNG时保留透明通道信息
        }


        $fntPath = dirname(__FILE__, 2);

        foreach ($text as $txt) {
            $txt += $bTxt;
            $color = $this->createColor($im, $txt['color']);

            $font = $txt['font'];
            if (!$font) $font = "{$fntPath}/fonts/simkai.ttf";
            else if ($font[0] !== '/') $font = "{$fntPath}/fonts/{$font}.ttf";
            if (!is_readable($font)) $font = "{$fntPath}/fonts/simkai.ttf";

            if ($txt['x'] === null) $txt['x'] = $txt['size'] * ($txt['percent'] - 1);
            if ($txt['y'] === null) $txt['y'] = $txt['size'];
            if ($txt['block']) {
                $temp = \imagettfbbox(ceil($txt['size']), $txt['angle'], $font, $txt['text']);//取得使用 TrueType 字体的文本的范围
                $option['width'] = ($temp[2] - $temp[0]);//文字宽
                $option['height'] = ($temp[1] - $temp[7]); //文字高

                \imagettftext($im,
                    $txt['size'],
                    $txt['angle'],
                    $txt['x'], $txt['y'],
                    $color,
                    $font,
                    $txt['text']);
            } else {
                for ($i = 0; $i < mb_strlen($txt['text']); $i++) {
                    imagettftext($im,
                        $txt['size'],
                        $txt['angle'],
                        $txt['x'], $txt['y'],
                        $color,
                        $font,
                        mb_substr($txt['text'], $i, 1, "utf8"));
                    if ($txt['vertical'])
                        $txt['y'] += ($txt['size'] * $txt['percent']);
                    else
                        $txt['x'] += ($txt['size'] * $txt['percent']);
                }
            }
        }

        $gdOption = [
            'save' => $option['save'],
            'filename' => '',
            'type' => IMAGETYPE_PNG,//文件类型
            'quality' => $option['quality'],
        ];

        $file = null;
        if ($option['save'] & 2) {
            $file = $this->getFileName($option['save'], $option['root'], $option['path'], 'png');
            $gdOption['filename'] = $file['filename'];
        }
        if ($option['save'] & 8) return $im;
        $pic = $this->draw($im, $gdOption);
        if ($option['save'] & 4) return $pic;

        return $file;
    }


    /**
     * 计算文字位置
     * @param $iw
     * @param $ih
     * @param $size
     * @param $font
     * @param $txt
     * @return array
     */
    private static function get_text_xy($iw, $ih, &$size, $font, $txt)
    {
        $temp = imagettfbbox(ceil($size), 0, $font, $txt);//取得使用 TrueType 字体的文本的范围
        var_dump($temp);
        $w = ($temp[2] - $temp[0]);//文字宽
        $h = ($temp[1] - $temp[7]); //文字高
        unset($temp);
        if ($w * 1.1 > $iw) {
            $size -= 2;
            return self::get_text_xy($iw, $ih, $size, $font, $txt);
        }
        $x = ($iw - $w) / 2;
        $y = $ih - ($ih - $h) / 2 - 10;
        return [$x, $y];
    }

}