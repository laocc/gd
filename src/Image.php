<?php

namespace esp\gd;


class Image extends BaseGD
{
    public function create(array $object)
    {
        if (is_readable($this->file)) {
            $bgFile = \getimagesize($this->file);
            $base_im = $this->createIM($this->file, $bgFile[2]);
//            throw new \Error("{$this->file} 不可读");
        } else {
            $base_im = \imagecreate($this->width, $this->height);
        }

        foreach ($object as $obj) {
            $obj['save'] = 8;

            switch ($obj['type']) {
                case 'rectangle'://矩形
                    //生成圆角的背景
                    $bgIM = $this->createRectangle($obj['width'], $obj['height'], $obj['color'], $obj['radius'] ?? 0);

                    //将背景写到图片上
                    \imagecopyresampled($base_im, $bgIM, $obj['x'], $obj['y'], 0, 0, $obj['width'], $obj['height'], $obj['width'], $obj['height']);

                    break;
                case 'image':
                    $info = \getimagesize($obj['file']);
                    $logoIM = $this->createIM($obj['file'], $info[2]);
                    \imagecopyresampled($base_im, $logoIM, $obj['x'], $obj['y'], 0, 0, $obj['width'], $obj['height'], $info[0], $info[1]);
                    \imagedestroy($logoIM);

                    break;
                case 'text':
                    $txt = new Text($obj);
                    $tIM = $txt->create([['x' => null, 'y' => null] + $obj], $obj);
                    if (!$obj['x']) {
                        $obj['x'] = ($this->width - $obj['width']) / 2;
                    } else if ($obj['x'] < 0) {
                        $obj['x'] = ($this->width + $obj['x']);
                    }
                    if (!$obj['y']) {
                        $obj['y'] = ($this->height - $obj['height']) / 2;
                    } else if ($obj['y'] < 0) {
                        $obj['y'] = ($this->height + $obj['y']);
                    }
                    \imagecopyresampled($base_im, $tIM, $obj['x'], $obj['y'], -1, -1,
                        $obj['width'], $obj['height'], $obj['width'] + 2, $obj['height'] + 2);

                    break;
                case 'qr':
                    $qr = new QrCode();
                    if (!isset($obj['width'])) $obj['width'] = 100;
                    if (!isset($obj['height'])) $obj['height'] = $obj['width'];
                    $qIM = $qr->create($obj);
                    \imagecopyresampled($base_im, $qIM, $obj['x'], $obj['y'], 0, 0, $obj['width'], $obj['height'], $obj['width'], $obj['height']);
                    break;
                default:
            }
        }


        $option = [
            'save' => $this->display,
            'filename' => '',
            'type' => IMAGETYPE_PNG,//文件类型
            'quality' => $this->quality,
        ];

        $file = null;
        if ($option['save'] & 2) {
            $file = $this->getFileName($option['save'], $option['root'], $option['path'], 'png');
            $option['filename'] = $file['filename'];
        }
        if ($option['save'] & 8) return $base_im;

        $gdImage = $this->draw($base_im, IMAGETYPE_PNG, $option['filename']);
        if ($option['save'] & 4) return $gdImage;

        return $file;
    }
}