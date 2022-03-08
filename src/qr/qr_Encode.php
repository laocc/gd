<?php


namespace esp\gd\qr;


class qr_Encode
{
    private $casesensitive = true;//区分大小写
    private $version = 0;
    private $hint = 2;

    /**
     * @param $option
     * @return resource
     */
    public function create(&$option)
    {
        $array = $this->encode($option['text'], $option['level']);
        $QR_PNG_MAXIMUM_SIZE = 1024;//最大宽度
        $maxSize = (int)($QR_PNG_MAXIMUM_SIZE / (count($array) + 2 * $option['margin']));
        $pixelPerPoint = min(max(1, $option['size']), $maxSize);
        return (new qr_Image([]))->image($array, $pixelPerPoint, $option);
    }


    public function encode($text, $level = 0)
    {
        $data = self::encodeString($text, $this->version, $level, $this->hint, $this->casesensitive);
        return self::binarize($data);
    }


    private static function encodeString($string, $version, $level, $hint, $casesensitive)
    {
        if (is_null($string) || $string == '\0' || $string == '') {
            throw new \Error('empty string');
        }
        if ($hint != 2 && $hint != 3) {
            throw new \Error('bad hint');
        }

        $input = new qr_Input($version, $level);
        if ($input == NULL) return NULL;

        $split = new qr_Split($string, $input, $hint);
        if (!$casesensitive) $split->toUpper();//不区分大小写
        $ret = $split->splitString();

        if ($ret < 0) {
            return NULL;
        }

        return self::encodeMask($input, -1);
    }


    private static function encodeMask(qr_Input $input, $mask)
    {
        if ($input->getVersion() < 0 || $input->getVersion() > 40) {
            throw new \Error('wrong version');
        }
        if ($input->getErrorCorrectionLevel() > 3) {
            throw new \Error('wrong level');
        }

        $raw = new qr_RawCode($input);
        $version = $input->getVersion();
        $width = qr_Spec::getWidth($version);
        $frame = qr_Spec::newFrame($version);

        $filler = new qr_FrameFiller($width, $frame);
        if (is_null($filler)) {
            return NULL;
        }

        // inteleaved data and ecc codes
        for ($i = 0; $i < $raw->dataLength + $raw->eccLength; $i++) {
            $code = $raw->getCode();
            $bit = 0x80;
            for ($j = 0; $j < 8; $j++) {
                $addr = $filler->nextXY();
                $filler->setFrameAt($addr, 0x02 | (($bit & $code) != 0));
                $bit = $bit >> 1;
            }
        }
        unset($raw);
        $j = qr_Spec::getRemainder($version);
        for ($i = 0; $i < $j; $i++) {
            $addr = $filler->nextXY();
            $filler->setFrameAt($addr, 0x02);
        }

        $frame = $filler->frame;
        unset($filler);

        $maskObj = new qr_Mask();
        $mask = $mask >= 0 ? $mask : 2;

        return $maskObj->makeMask($width, $frame, $mask, $input->getErrorCorrectionLevel());
    }


    //------进行二值化处理----------------------------------------------------------------
    private static function binarize($frame)
    {
        $len = count($frame);
        foreach ($frame as &$frameLine) {
            for ($i = 0; $i < $len; $i++) {
                $frameLine[$i] = (ord($frameLine[$i]) & 1) ? '1' : '0';
            }
        }
        return $frame;
    }

}
