<?php

namespace esp\gd\qr;

use esp\gd\BaseGD;

class qr_Image extends BaseGD
{
    /**
     * @param array $frame
     * @param int $pixelPerPoint
     * @param $option
     * @return resource
     */
    public function image(array $frame, $pixelPerPoint , $option)
    {
        $h = count($frame);
        $w = strlen($frame[0]);

        $imgW = $w + 2 * $option['margin'];//在1像素时的宽度
        $imgH = $h + 2 * $option['margin'];

        if ($option['width'] === 0) {
            $width = $imgW * $pixelPerPoint;//乘以实际像数后的宽度
            $height = $imgH * $pixelPerPoint;
        } else {//指定了大小
            $width = $height = $option['width'];
            $pixelPerPoint = $width / $imgW;
        }

        if (preg_match('/^([a-z]+)|(\#[a-f0-9]{3})|(\#[a-f0-9]{6})$/i', $option['background'])) {
            $resource_im = \imagecreate($imgW, $imgH);
            $bgColor = $this->createColor($resource_im, $option['background']);//二维码的背景色
            \imagefill($resource_im, 0, 0, $bgColor);//填充背景色
        } else {
            $resource_im = $this->createIM($option['background']);
        }

        //最成最终二维码的尺寸：每点为1像素时的宽度，乘设定的每个像素的实际宽度
        //不要用imagecreatetruecolor，否则后面抽除颜色时有问题
        $base_im = \imagecreate($width, $height);

        //二维码的主色，若主色是图片，则这儿得到的是#000的黑色
        $qrColor = $this->createColor($resource_im, $option['color']);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ($frame[$y][$x] == '1') {
                    \imagesetpixel($resource_im, $x + $option['margin'], $y + $option['margin'], $qrColor);
                }
            }
        }

        //把刚才生成的二维码放大，并放到实际大小的二维码上去
        \imagecopyresampled($base_im, $resource_im, 0, 0, 0, 0, $width, $height, $imgW, $imgH);
        \imagedestroy($resource_im);


        //用图片做前景色
        if (is_file($option['color'])) {
            //先把图片复制到空白容器里去
            $IM = \imagecreatetruecolor($width, $height);//用真彩色
            $info = \getimagesize($option['color']);
            $PM = $this->createIM($option['color'], $info[2]);

            //原图写入临时容器，缩放
            \imagecopyresampled($IM, $PM, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
            \imagedestroy($PM);

            //然后把前面生成的二维码，前景色部分扣掉
            \imagecolortransparent($base_im, $qrColor);

            //最后把扣掉的二维码合并到图片的容器里
            \imagecopyresampled($IM, $base_im, 0, 0, 0, 0, $width, $height, $width, $height);
            \imagedestroy($base_im);
            $base_im = $IM;
        }

        //加LOGO
        if (!!$option['logo'] and $option['level'] > 0 and is_file($option['logo'])) {
            $info = \getimagesize($option['logo']);
            if ($info[0] > $info[1]) {//长方形
                $logoWidth = $info[1];
            } else {
                $logoWidth = $info[0];
            }

            $logoWH = $width * 0.2;//计算LOGO部分的尺寸，含边框，即：整体二维码的五分之一
            $logoXY = ($width - $logoWH) / 2;//计算LOGO开始的XY点
            $bgWidth = $logoWidth * 2;

            //LOGO外部留空像素
            $lgBorder = $pixelPerPoint * 0.5;

            //圆角半径
            $radius = $bgWidth * 0.15;

            //生成圆角的背景
            $bgIM = $this->createRectangle($bgWidth, $bgWidth, $option['logo_border'], $radius);

            //将背景写到图片上
            \imagecopyresampled($base_im, $bgIM, $logoXY - $lgBorder, $logoXY - $lgBorder, 0, 0, $logoWH + $lgBorder * 2, $logoWH + $lgBorder * 2, $bgWidth, $bgWidth);

            //创建一个圆角遮罩层
            $filter = $this->createCircle($logoWidth, $logoWidth, $option['logo_border'], $radius * 0.5);

            //将圆角遮罩层合并到LOGO上
            $logoIM = $this->createIM($option['logo'], $info[2]);
            \imagecopyresampled($logoIM, $filter, 0, 0, 0, 0, $logoWidth, $logoWidth, $logoWidth, $logoWidth);

            //将LOGO写到图上
            \imagecopyresampled($base_im, $logoIM, $logoXY, $logoXY, 0, 0, $logoWH, $logoWH, $logoWidth, $logoWidth);


            \imagedestroy($logoIM);
            \imagedestroy($filter);
            \imagedestroy($bgIM);
        }

        //加底图
        if (!!$option['parent'] and is_file($option['parent'])) {
            $sInfo = \getimagesize($option['parent']);
            $shIM = $this->createIM($option['parent'], $sInfo[2]);

            if ($option['width'] === 0) {
                $width = $imgW * $pixelPerPoint;//乘以实际像数后的宽度
                $height = $imgH * $pixelPerPoint;
            } else {//指定了大小
                $width = $height = $option['width'];
            }

            $x = (isset($option['parent_x']) and is_int($option['parent_x'])) ? $option['parent_x'] : ($sInfo[0] - $width) / 2;
            $y = (isset($option['parent_y']) and is_int($option['parent_y'])) ? $option['parent_y'] : ($sInfo[1] - $width) / 2;

            //加阴影
            if (!!$option['shadow']) {
                $shadow_im = \imagecreate($width, $height);
                $shadow_color = $this->createColor($shadow_im, $option['shadow'], $option['shadow_alpha']);
                \imagefill($shadow_im, 0, 0, $shadow_color);
                $shadow_x = \intval($option['shadow_x']);
                $shadow_y = \intval($option['shadow_y']);
                \imagecopyresampled($shIM, $shadow_im, $x + $shadow_x, $y + $shadow_y, 0, 0, $width, $height, $width, $height);
            }

            \imagecopyresampled($shIM, $base_im, $x, $y, 0, 0, $width, $height, $width, $height);
            $base_im = $shIM;


        }


        return $base_im;
    }


}
