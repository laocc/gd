<?php
declare(strict_types=1);

namespace laocc\thumbnail\gd;


class Gd
{
    const Quality = 80;    //JPG默认质量，对所有方法都有效
    private $backup = [];

    /**
     * 同一文件只备份一次
     * @param $file
     * @param bool $force
     */
    private static function backup($file, $force = false)
    {
        $mdKey = md5($file);
        if (!$force and isset(self::$backup[$mdKey])) return;
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (is_file("{$file}.{$ext}")) return;
        copy($file, "{$file}.{$ext}");
        self::$backup[$mdKey] = 1;
    }

    /**
     * 用tclip插件生成缩略图
     * 关于tclip：https://github.com/exinnet/tclip
     * @param string $file
     * @param array $option
     * @return bool
     */
    private static function thumbs_tclip(string $file, array $option = [])
    {
        $option += ['save' => 1, 'cache' => true];

        if (!function_exists('tclip')) return self::thumbs_create($file, $option);

        if (!isset($option['source']) or !is_file($option['source'])) return '源文件不存在';//源文件不存在
        $watermark_text = '';
        $create = \tclip($option['source'], $file, $option['width'], $option['height']);

        if ($create === true) {
            $type = \exif_imagetype($file);
            $im = self::createIM($file, $type);
            $option = [
                'save' => 0,//0：只显示，1：只保存，2：即显示也保存
                'cache' => $option['cache'],//允许缓存
                'type' => $type,//文件类型
                'quality' => self::Quality,
            ];
            self::draw($im, $option);
            return true;
        } else {
            return self::thumbs_create($file, $option);
        }
    }


    public static function thumbs_create(string $file, array $option = [])
    {
        $option += [
            'mode' => 'x',//xvz之一
            'background' => '#ffffff',//v模式下缩图的背景色
            'alpha' => false,//v模式时若遇png，背景部分是否写成透明背景
            'width' => 100,
            'height' => 0,
            'save' => 1,//0：只显示，1：只保存，2：即显示也保存
            'ext' => ".png",
            'cache' => true,
        ];

        if (!isset($option['source']) or !is_file($option['source'])) return '源文件不存在';//源文件不存在
        if ($option['width'] === 0 and $option['height'] === 0) return '缩略图宽高不可都为0';//若宽高都为0则不生成

        $PicV = array();
        $PicV['info'] = getimagesize($option['source']);
        if (!$PicV['info']) return '源文件不是有效图片';

        $PicV['oldWidth'] = $PicV['info'][0];//源图宽
        $PicV['oldHeight'] = $PicV['info'][1];//源图高

        $oldIM = self::createIM($option['source'], $PicV['info'][2]);
        if (!$oldIM) return '源文件无法创建成资源';

        //若宽高任一值为0,则进行等比缩小
        if ($option['height'] === 0) {
            $option['height'] = $option['width'] * ($PicV['oldHeight'] / $PicV['oldWidth']);
        } else if ($option['width'] === 0) {
            $option['width'] = $option['height'] * ($PicV['oldWidth'] / $PicV['oldHeight']);
        }

        //建目标模式
        $newIM = imagecreatetruecolor($option['width'], $option['height']);

        //PNG写透明背景
        if ($PicV['info'][2] === 3 and $option['alpha']) {
            $alpha = self::createColor($newIM, '#000', 127);
            imagefill($newIM, 0, 0, $alpha);
            imagesavealpha($newIM, true);

        } else {
            //其他模式写设定的颜色背景
            $tColor = self::createColor($newIM, $option['background']);
            imagefilledrectangle($newIM, 0, 0, $option['width'], $option['height'], $tColor);
        }

        //计算各自宽高比,
        $PicV['nRatio'] = ($option['width'] / $option['height']);                    //新图宽高比
        $PicV['oRatio'] = ($PicV['oldWidth'] / $PicV['oldHeight']);  //老图宽高比

        //裁切形状:0正方形,1扁形,2竖形
        $PicV['cutShape'] = ($PicV['nRatio'] === $PicV['oRatio']) ? 0 : (($PicV['nRatio'] > $PicV['oRatio']) ? 2 : 1);

        //先默认值
        $oldWidth = $PicV['oldWidth'];
        $oldHeight = $PicV['oldHeight'];
        $x = $y = 0;//源图坐标
        $X = $Y = 0;//新图坐标


        //直接缩放
        if ($option['mode'] === 'z') {


        } elseif ($option['mode'] === 'x') {//以目标大小,最大化截取，裁切掉不等比部分
            switch ($PicV['cutShape']) {
                case 0://等比
                    break;
                case 1:    //从源图中间截取,删除左右多余
                    $percent = $PicV['oldHeight'] / $option['height'];
                    $oldWidth = $option['width'] * $percent;
                    $x = ($PicV['oldWidth'] - $oldWidth) / 2;
                    break;
                case 2://从源图中间截取,删除上下多余
                    $percent = $PicV['oldWidth'] / $option['width'];
                    $oldHeight = $option['height'] * $percent;
                    $y = ($PicV['oldHeight'] - $oldHeight) / 2;
                    break;
                default:
            }


        } elseif ($option['mode'] === 'v') {//以原图大小，全部保留，不够部分留白
            switch ($PicV['cutShape']) {
                case 0://等比
                    break;
                case 1:    //最大化截取,上下留白
                    $percent = $option['width'] / $PicV['oldWidth'];
                    //$percent=($percent>1?1:$percent);
                    $Y = ($option['height'] - ($PicV['oldHeight'] * $percent)) / 2;
                    $option['height'] = $PicV['oldHeight'] * $percent;
                    break;
                case 2://最大化截取,左右留白
                    $percent = $option['height'] / $PicV['oldHeight'];
                    //$percent=($percent>1?1:$percent);
                    $X = ($option['width'] - ($PicV['oldWidth'] * $percent)) / 2;
                    $option['width'] = $PicV['oldWidth'] * $percent;
                    break;
                default:
            }
        }

        //输入并缩放
        imagecopyresampled(
            $newIM,//目标图
            $oldIM,//源图,即上面存入的图
            $X, $Y,//目标图的XY
            $x, $y,//源图XY
            $option['width'], $option['height'],//目标图宽高
            $oldWidth, $oldHeight//源图宽高
        );

        $option = [
            'save' => $option['save'],//0：只显示，1：只保存，2：即显示也保存
            'filename' => $file,
            'type' => $PicV['info'][2],//文件类型
            'cache' => $option['cache'],//允许缓存
            'quality' => self::Quality,
        ];
        self::draw($newIM, $option);
        imagedestroy($oldIM);
        return true;
    }

    public static function createColor(&$im, $color = '#000000', $alpha = 0)
    {
        if (is_int($color)) list($color, $alpha) = ['#000000', $color];
        list($R, $G, $B) = self::getRGBColor($color);
        if ($alpha > 0) {//透明色
            if ($alpha > 100) $alpha = 100;
            $alpha = $alpha * 1.27;
            return imagecolorallocatealpha($im, $R, $G, $B, $alpha);
        } else {
            return imagecolorallocate($im, $R, $G, $B);
        }
    }

    public static function getRGBColor($color)
    {
        if (is_array($color)) {
            if (count($color) === 1) {
                list($R, $G, $B) = [mt_rand(0, $color[0]), mt_rand(0, $color[0]), mt_rand(0, $color[0])];

            } else if (count($color) === 2) {//是一个取值范围
                list($R, $G, $B) = [mt_rand(...$color), mt_rand(...$color), mt_rand(...$color)];

            } else {
                list($R, $G, $B) = $color;
            }
        } else {
            $color = preg_replace('/^[a-z]+$/i', self::getColorHex('$1'), $color);//颜色名换色值
            $color = preg_replace('/^\#([a-f0-9])([a-f0-9])([a-f0-9])$/i', '#$1$1$2$2$3$3', $color);//短色值换为标准色值
            $color = preg_match('/^\#[a-f0-9]{6}$/i', $color) ? $color : '#000000';//不是标准色值的，都当成黑色
            $R = hexdec(substr($color, 1, 2));
            $G = hexdec(substr($color, 3, 2));
            $B = hexdec(substr($color, 5, 2));
        }
        return [$R, $G, $B];
    }


    /**
     * 根据颜色名称转换为色值
     * @param $code
     * @return int
     */
    public static function getColorHex($code)
    {
        switch (strtolower($code)) {
            case 'white':
                return '#ffffff';
            case 'black':
                return '#000000';
            case 'maroon':
                return '#800000';
            case 'red':
                return '#ff0000';
            case 'orange':
                return '#ffa500';
            case 'yellow':
                return '#ffff00';
            case 'olive':
                return '#808000';
            case 'purple':
                return '#800080';
            case 'fuchsia':
                return '#ff00ff';
            case 'lime':
                return '#00ff00';
            case 'green':
                return '#008000';
            case 'navy':
                return '#000080';
            case 'blue':
                return '#0000ff';
            case 'aqua':
                return '#00ffff';
            case 'teal':
                return '#008080';
            case 'silver':
                return '#c0c0c0';
            case 'gray':
                return '#808080';
            default:
                return '#ffffff';
        }

//
//        $args[0] = intval($args[0]);
//        $this->r = ($args[0] & 0xff0000) >> 16;
//        $this->g = ($args[0] & 0x00ff00) >> 8;
//        $this->b = ($args[0] & 0x0000ff);
    }


    public static function createIM($pic, $type = 0)
    {
        if (is_bool($type)) {
            return imagecreatefromstring($pic);
        }
        $type = $type ?: \exif_imagetype($pic);
        switch ($type) {
            case IMAGETYPE_GIF:
                $PM = @imagecreatefromgif($pic);
                break;
            case IMAGETYPE_JPEG:
                $PM = @imagecreatefromjpeg($pic);
                break;
            case IMAGETYPE_PNG:
                $PM = @imagecreatefrompng($pic);
                break;
            case IMAGETYPE_WBMP:
                $PM = @imagecreatefromwbmp($pic);
                break;
            case IMAGETYPE_XBM:
                $PM = @imagecreatefromxbm($pic);
                break;
            case IMAGETYPE_ICO:
                //ICON
                $PM = null;
                break;
            default:
                $PM = self::createFromImg($pic);
                break;
        }
        return $PM;
    }


    /**
     * 从BMP读取为资源
     * @param string $filename
     * @return null|resource
     */
    public static function createFromImg($filename)
    {
        //打开文件，若出错则退
        if (!$fr = @fopen($filename, "rb")) return null;

        //1 : Chargement des ent tes FICHIER
        $FILE = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($fr, 14));
        if ($FILE['file_type'] != 19778) return null;

        //2 : Chargement des ent tes BMP
        $BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel/Vcompression/Vsize_bitmap/Vhoriz_resolution/Vvert_resolution/Vcolors_used/Vcolors_important', fread($fr, 40));

        $BMP['colors'] = pow(2, $BMP['bits_per_pixel']);
        $BMP['size_bitmap'] = ($BMP['size_bitmap'] === 0) ? $FILE['file_size'] - $FILE['bitmap_offset'] : $BMP['size_bitmap'];
        $BMP['bytes_per_pixel'] = $BMP['bits_per_pixel'] / 8;
        $BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
        $BMP['decal'] = ($BMP['width'] * $BMP['bytes_per_pixel'] / 4);
        $BMP['decal'] -= floor($BMP['width'] * $BMP['bytes_per_pixel'] / 4);
        $BMP['decal'] = 4 - (4 * $BMP['decal']);
        $BMP['decal'] = ($BMP['decal'] === 4) ? 0 : $BMP['decal'];

        //3 : Chargement des couleurs de la palette
        $PALETTE = array();
        if ($BMP['colors'] < 16777216) {
            $PALETTE = unpack('V' . $BMP['colors'], fread($fr, $BMP['colors'] * 4));
        }

        //4 : Cr ation de l'image
        $IMG = fread($fr, $BMP['size_bitmap']);
        $VIDE = chr(0);

        $res = imagecreatetruecolor($BMP['width'], $BMP['height']);
        $P = 0;
        $Y = $BMP['height'] - 1;
        while ($Y >= 0) {
            $X = 0;
            while ($X < $BMP['width']) {
                if ($BMP['bits_per_pixel'] === 24)
                    $COLOR = unpack("V", substr($IMG, $P, 3) . $VIDE);
                elseif ($BMP['bits_per_pixel'] === 16) {
                    $COLOR = unpack("n", substr($IMG, $P, 2));
                    $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                } elseif ($BMP['bits_per_pixel'] === 8) {
                    $COLOR = unpack("n", $VIDE . substr($IMG, $P, 1));
                    $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                } elseif ($BMP['bits_per_pixel'] === 4) {
                    $COLOR = unpack("n", $VIDE . substr($IMG, floor($P), 1));
                    if (($P * 2) % 2 === 0)
                        $COLOR[1] = ($COLOR[1] >> 4);
                    else
                        $COLOR[1] = ($COLOR[1] & 0x0F);
                    $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                } elseif ($BMP['bits_per_pixel'] === 1) {
                    $COLOR = unpack("n", $VIDE . substr($IMG, floor($P), 1));
                    if (($P * 8) % 8 === 0)
                        $COLOR[1] = $COLOR[1] >> 7;
                    elseif (($P * 8) % 8 === 1)
                        $COLOR[1] = ($COLOR[1] & 0x40) >> 6;
                    elseif (($P * 8) % 8 === 2)
                        $COLOR[1] = ($COLOR[1] & 0x20) >> 5;
                    elseif (($P * 8) % 8 === 3)
                        $COLOR[1] = ($COLOR[1] & 0x10) >> 4;
                    elseif (($P * 8) % 8 === 4)
                        $COLOR[1] = ($COLOR[1] & 0x8) >> 3;
                    elseif (($P * 8) % 8 === 5)
                        $COLOR[1] = ($COLOR[1] & 0x4) >> 2;
                    elseif (($P * 8) % 8 === 6)
                        $COLOR[1] = ($COLOR[1] & 0x2) >> 1;
                    elseif (($P * 8) % 8 === 7)
                        $COLOR[1] = ($COLOR[1] & 0x1);
                    $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                } else
                    return null;
                imagesetpixel($res, $X, $Y, $COLOR[1]);
                $X++;
                $P += $BMP['bytes_per_pixel'];
            }
            $Y--;
            $P += $BMP['decal'];
        }

        fclose($fr); //关闭文件资源
        return $res;
    }

    public static function draw($im, array $option)
    {
        $option += [
            'save' => 0,//0：只显示，1：只保存，2：即显示也保存，3：返回GD数据流
            'filename' => null,
            'type' => IMAGETYPE_PNG,//文件类型
            'quality' => 80,
            'version' => isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : '',
        ];
        if (preg_match('/^\d\.\d$/', $option['version'])) {
            $option['version'] = "HTTP/{$option['version']}";
        }

        //保存到文件
        if ($option['save'] === 1 or $option['save'] === 2) {
            switch ($option['type']) {
                case 'gif':
                case IMAGETYPE_GIF:
                    $option['type'] = IMAGETYPE_GIF;
                    imagegif($im, $option['filename']);
                    break;
                case 'jpg':
                case 'jpeg':
                case IMAGETYPE_JPEG:
                    $option['type'] = IMAGETYPE_JPEG;
                    imagejpeg($im, $option['filename'], $option['quality'] ?: 80);
                    break;
                case 'png':
                case IMAGETYPE_PNG:
                    $option['type'] = IMAGETYPE_PNG;
                    imagepng($im, $option['filename']);
                    break;
                default:
                    imagegd2($im, $option['filename']);
            }
        } elseif ($option['save'] === 3) {//返回base64

            //"<img src='data:image/png;base64,{$data}'>";

//            ob_end_clean();//清空所有缓存
            ob_start();//清除前先打开，否则在有些情况下清空缓存会失败
            imagepng($im);
            $data = ob_get_contents();
            ob_end_clean();
            imagedestroy($im);
            if (!empty($data)) {
                return base64_encode($data);
            } else {
                return '';
            }
        }

        //输出
        if (php_sapi_name() !== 'cli' and ($option['save'] === 0 or $option['save'] === 2)) {
            ob_start();//清除前先打开，否则在有些情况下清空缓存会失败
            ob_end_clean();//清空所有缓存
            header("{$option['version']} 200", true, 200);
            header('Content-type:' . image_type_to_mime_type($option['type']), true);
            header('Access-Control-Allow-Origin: *', true);
            header('Create-by: GD', true);
            header('Save-by: ' . $option['save'], true);

            //没有明确是否缓存，或明确了不缓存
            if (!isset($option['cache']) or !$option['cache']) {
                header('Cache-Control:no-cache,must-revalidate,no-store', true);
                header('Pramga: no-cache', true);
                header('Cache-Info: no cache', true);
            }

            switch ($option['type']) {
                case IMAGETYPE_GIF:
                    imagegif($im);
                    break;
                case IMAGETYPE_JPEG:
                    imagejpeg($im, null, $option['quality'] ?: 80);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($im);
                    break;
                default:
                    imagegd2($im);
            }
        }
        imagedestroy($im);
        return true;
    }

}