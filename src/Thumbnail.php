<?php
declare(strict_types=1);

namespace esp\gd;

use function esp\helper\replace_array;

class Thumbnail extends BaseGD
{

    /**
     * 生成缩略图URL
     * @param array $conf
     * @param string $img
     * @param int $size
     * @param string|null $rand
     * @param string $mode
     * @return string
     * @throws \Exception
     */
    public static function src(array $conf, string $img, $size = 2, string $rand = null, string $mode = 'x')
    {
        if (!$img) return '';
        if ($size === 0) return $img;
        $info = pathinfo($img);

        if (is_int($size)) {
            $width = $size;
            if ($width < 10) $width = $conf['size'][$width] ?? 60;
            $height = $width;
            if (is_array($width)) [$width, $height] = $width;
        } elseif (is_array($size)) {
            $width = $size[0];
            $height = $size[1] ?? $width;
        } else {
            throw new \Exception("size只能为int或array", 505);
        }

        $val = [];
        $val['img'] = $img;
        $val['width'] = substr("000{$width}", -3);
        $val['height'] = substr("000{$height}", -3);
        $val['mode'] = $mode;
        $val['ext'] = $info['extension'] ?? 'png';
        $val['rand'] = $rand ?: date('Y');

        $src = $conf['src'] ?? '';
        if (!$src) $src = '{img}_{width}{mode}{height}.{ext}?_r={rand}';

        return replace_array($src, $val);
    }

    /**
     * 站点入口的地方调用     * 先判断当前访问是不是一个缩略图格式，
     * 若是，直接创建且后面不再执行
     * @param array $conf
     * @param string $uri
     * @return bool
     */
    public function create(array $conf, string $uri): bool
    {
        $r = strpos($uri, '?');
        if ($r > 0) $uri = substr($uri, 0, $r);
        $this->display = $conf['display'] ?? 3;

        $mch = preg_match($conf['pattern'], $uri, $matches);
        if ($mch) {
            if (!isset($matches['img'])) {
                echo "正则表达式中未含 img 项:";
                print_r($matches);
                return false;
            }

            $option = [];
            $option['source'] = realpath($this->root . '/' . $matches['img']);
            $option['ext'] = strtolower($matches['ext'] ?? 'png');
            $option['mode'] = strtolower($matches['mode'] ?? 'x');////xvz之一
            $option['width'] = intval($matches['width'] ?? 32);
            $option['height'] = intval($matches['height'] ?? 32);

            if (!$option['source']) {
                echo "source [ ROOT/{$matches['img']} ] file not exists";
                return false;
            }

            //源文件不存在
            if (!is_file($option['source'])) {
                echo "source [ ROOT/{$matches['img']} ] file not exists";
                return false;
            }

            //加水印
            if ($this->markIcon) {
                $ext = '.' . pathinfo($option['source'], PATHINFO_EXTENSION);
                //position=九宫格序号

                if (!is_file($option['source'] . $ext)) {
                    $config = [
                        'backup' => true,
                        'order' => 0,
                        'img' => ['file' => $this->markIcon],
                        'position' => 9
                    ];
//                $mark = new Mark();
//                Image::mark($file, $config);
                }
            }

            $saveFile = $this->root . $uri;
            if (!is_readable(($fp = dirname($saveFile)))) mkdir($fp, 0740, true);

            if ($this->tclip and $option['model'] === 'x') {
                $create = $this->thumbs_tclip($saveFile, $option);
            } else {
                $create = $this->thumbs_create($saveFile, $option);
            }

            if (!empty($create)) {
                echo $create;
                return false;
            }

            return true;
        }

        return false;
    }


    private function thumbs_create(string $file, array $option): string
    {
        $option += [
            'mode' => 'x',//xvz之一
            'background' => '#ffffff',//v模式下缩图的背景色
            'alpha' => false,//v模式时若遇png，背景部分是否写成透明背景
            'width' => 100,
            'height' => 0,
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

        $oldIM = $this->createIM($option['source'], $PicV['info'][2]);
        if (!$oldIM) return '源文件无法创建成资源';

        //若宽高任一值为0,则进行等比缩小
        if ($option['height'] === 0) {
            $option['height'] = intval($option['width'] * ($PicV['oldHeight'] / $PicV['oldWidth']));
        } else if ($option['width'] === 0) {
            $option['width'] = intval($option['height'] * ($PicV['oldWidth'] / $PicV['oldHeight']));
        }

        //建目标模式
        $newIM = imagecreatetruecolor($option['width'], $option['height']);

        //PNG写透明背景
        if ($PicV['info'][2] === 3 and $option['alpha']) {
            $alpha = $this->createColor($newIM, '#000', 127);
            imagefill($newIM, 0, 0, $alpha);
            imagesavealpha($newIM, true);

        } else {
            //其他模式写设定的颜色背景
            $tColor = $this->createColor($newIM, $option['background']);
            imagefilledrectangle($newIM, 0, 0, $option['width'], $option['height'], $tColor);
        }

        $PicV['nRatio'] = ($option['width'] / $option['height']);    //新图宽高比
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
            intval($X), intval($Y),//目标图的XY
            intval($x), intval($y),//源图XY
            intval($option['width']), intval($option['height']),//目标图宽高
            intval($oldWidth), intval($oldHeight)//源图宽高
        );

        $option = [
            'filename' => $file,
            'type' => $PicV['info'][2],//文件类型
            'cache' => $option['cache'],//允许缓存
            'quality' => $this->quality,
        ];

        if ($this->debug) {
            return print_r($option, true);
        }

        $this->draw($newIM, IMAGETYPE_PNG, $file);
        imagedestroy($oldIM);
        return '';
    }

    private function thumbs_tclip(string $file, array $option): string
    {
        $mark = '';
        $create = \tclip($option['source'], $file, $option['width'], $option['height'], $mark);

        if ($create === true) {
            $type = \exif_imagetype($file);
            $im = $this->createIM($file, $type);
            $this->display = 1;//仅直接显示
            $this->draw($im, $type, $file);
            return '';
        } else {
            return $this->thumbs_create($file, $option);
        }
    }


}