<?php

namespace esp\gd;


class Image extends BaseGD
{
    public function create(array $object)
    {
        if (is_readable($this->file)) {
            $bgFile = \getimagesize($this->file);
            $base_im = $this->createIM($this->file, $bgFile[2]);
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
                    \imagecopyresampled($base_im, $bgIM,
                        intval($obj['x']), intval($obj['y']), 0, 0,
                        intval($obj['width']), intval($obj['height']),
                        intval($obj['width']), intval($obj['height']));
                    \imagedestroy($bgIM);
                    break;
                case 'image':
                    $info = \getimagesize($obj['file']);
                    $logoIM = $this->createIM($obj['file'], $info[2]);
                    \imagecopyresampled($base_im, $logoIM,
                        intval($obj['x']), intval($obj['y']), 0, 0,
                        intval($obj['width']), intval($obj['height']),
                        intval($info[0]), intval($info[1]));
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
                    \imagecopyresampled($base_im, $tIM,
                        intval($obj['x']), intval($obj['y']), -1, -1,
                        intval($obj['width']), intval($obj['height']),
                        intval($obj['width'] + 2), intval($obj['height'] + 2));
                    \imagedestroy($tIM);
                    break;
                case 'qr':
                    $qr = new QrCode();
                    if (!isset($obj['width'])) $obj['width'] = 100;
                    if (!isset($obj['height'])) $obj['height'] = $obj['width'];
                    $qIM = $qr->create($obj);
                    \imagecopyresampled($base_im, $qIM,
                        intval($obj['x']), intval($obj['y']), 0, 0,
                        intval($obj['width']), intval($obj['height']),
                        intval($obj['width']), intval($obj['height']));
                    \imagedestroy($qIM);
                    break;
                default:
            }
        }

        if ($this->display & 8) return $base_im;

        $name = md5(uniqid(mt_rand(), true));
        if (isset($conf['name'])) $name = $conf['name'];
        $file = $this->getFileName($this->root, $this->path, $name, 'png');
        $gdImage = $this->draw($base_im, IMAGETYPE_PNG, $file['filename'] ?? '');
        if ($this->display & 4) return $gdImage;

        return $file;
    }
}