<?php


namespace esp\gd\qr;


class qr_InputItem
{

    public $mode;
    public $size;
    public $data;
    public $bstream;

    public function __construct($mode, $size, $data, $bstream = null)
    {
        $setData = array_slice($data, 0, $size);

        if (count($setData) < $size) {
            $setData = array_merge($setData, array_fill(0, $size - count($setData), 0));
        }

        if (!qr_Input::check($mode, $size, $setData)) {
            throw new \Error('Error m:' . $mode . ',s:' . $size . ',d:' . join(',', $setData));
        }

        $this->mode = $mode;
        $this->size = $size;
        $this->data = $setData;
        $this->bstream = $bstream;
    }

    public function encodeModeNum($version)
    {
        try {

            $words = (int)($this->size / 3);
            $bs = new qr_BitStream();

            $val = 0x1;
            $bs->appendNum(4, $val);
            $bs->appendNum(qr_Spec::lengthIndicator(0, $version), $this->size);

            for ($i = 0; $i < $words; $i++) {
                $val = (ord($this->data[$i * 3]) - ord('0')) * 100;
                $val += (ord($this->data[$i * 3 + 1]) - ord('0')) * 10;
                $val += (ord($this->data[$i * 3 + 2]) - ord('0'));
                $bs->appendNum(10, $val);
            }

            if ($this->size - $words * 3 == 1) {
                $val = ord($this->data[$words * 3]) - ord('0');
                $bs->appendNum(4, $val);
            } else if ($this->size - $words * 3 == 2) {
                $val = (ord($this->data[$words * 3]) - ord('0')) * 10;
                $val += (ord($this->data[$words * 3 + 1]) - ord('0'));
                $bs->appendNum(7, $val);
            }

            $this->bstream = $bs;
            return 0;

        } catch (\Error $e) {
            return -1;
        }
    }


    public function encodeModeAn($version)
    {
        try {
            $words = (int)($this->size / 2);
            $bs = new qr_BitStream();

            $bs->appendNum(4, 0x02);
            $bs->appendNum(qr_Spec::lengthIndicator(1, $version), $this->size);

            for ($i = 0; $i < $words; $i++) {
                $val = (int)qr_Input::lookAnTable(ord($this->data[$i * 2])) * 45;
                $val += (int)qr_Input::lookAnTable(ord($this->data[$i * 2 + 1]));

                $bs->appendNum(11, $val);
            }

            if ($this->size & 1) {
                $val = qr_Input::lookAnTable(ord($this->data[$words * 2]));
                $bs->appendNum(6, $val);
            }

            $this->bstream = $bs;
            return 0;

        } catch (\Error $e) {
            return -1;
        }
    }


    public function encodeMode8($version)
    {
        try {
            $bs = new qr_BitStream();

            $bs->appendNum(4, 0x4);
            $bs->appendNum(qr_Spec::lengthIndicator(2, $version), $this->size);

            for ($i = 0; $i < $this->size; $i++) {
                $bs->appendNum(8, ord($this->data[$i]));
            }

            $this->bstream = $bs;
            return 0;

        } catch (\Error $e) {
            return -1;
        }
    }


    public function encodeModeStructure()
    {
        try {
            $bs = new qr_BitStream();

            $bs->appendNum(4, 0x03);
            $bs->appendNum(4, ord($this->data[1]) - 1);
            $bs->appendNum(4, ord($this->data[0]) - 1);
            $bs->appendNum(8, ord($this->data[2]));

            $this->bstream = $bs;
            return 0;

        } catch (\Error $e) {
            return -1;
        }
    }


    public function estimateBitStreamSizeOfEntry($version)
    {
        $bits = 0;
        $version = $version ?: 1;
        switch ($this->mode) {
            case 0:
                $bits = qr_Input::estimateBitsModeNum($this->size);
                break;
            case 1:
                $bits = qr_Input::estimateBitsModeAn($this->size);
                break;
            case 2:
                $bits = qr_Input::estimateBitsMode8($this->size);
                break;
            //case 3:        $bits = qr_Input::estimateBitsModeKanji($this->size);break;
            case 4:
                return 20;
            default:
                return 0;
        }

        $l = qr_Spec::lengthIndicator($this->mode, $version);
        $m = 1 << $l;
        $num = (int)(($this->size + $m - 1) / $m);

        $bits += $num * (4 + $l);

        return $bits;
    }


    public function encodeBitStream($version)
    {
        try {

            unset($this->bstream);
            $words = qr_Spec::maximumWords($this->mode, $version);

            if ($this->size > $words) {

                $st1 = new qr_InputItem($this->mode, $words, $this->data);
                $st2 = new qr_InputItem($this->mode, $this->size - $words, array_slice($this->data, $words));

                $st1->encodeBitStream($version);
                $st2->encodeBitStream($version);

                $this->bstream = new qr_BitStream();
                $this->bstream->append($st1->bstream);
                $this->bstream->append($st2->bstream);

                unset($st1);
                unset($st2);

            } else {

                $ret = 0;

                switch ($this->mode) {
                    case 0:
                        $ret = $this->encodeModeNum($version);
                        break;
                    case 1:
                        $ret = $this->encodeModeAn($version);
                        break;
                    case 2:
                        $ret = $this->encodeMode8($version);
                        break;
                    //case 3:        $ret = $this->encodeModeKanji($version);break;
                    case 4:
                        $ret = $this->encodeModeStructure();
                        break;

                    default:
                        break;
                }

                if ($ret < 0)
                    return -1;
            }

            return $this->bstream->size();

        } catch (\Error $e) {
            return -1;
        }
    }
}
