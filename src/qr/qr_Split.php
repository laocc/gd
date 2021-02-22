<?php


namespace esp\gd\qr;

class qr_Split
{

    private $dataStr = '';
    private $input;
    private $modeHint;


    public function __construct($dataStr, qr_Input $input, $modeHint)
    {
        $this->dataStr = $dataStr;
        $this->input = $input;
        $this->modeHint = $modeHint;
    }


    private static function isDigitat($str, $pos)
    {
        if ($pos >= strlen($str))
            return false;

        return ((ord($str[$pos]) >= ord('0')) && (ord($str[$pos]) <= ord('9')));
    }


    private static function isalnumat($str, $pos)
    {
        if ($pos >= strlen($str))
            return false;

        return (qr_Input::lookAnTable(ord($str[$pos])) >= 0);
    }


    private function identifyMode($pos)
    {
        if ($pos >= strlen($this->dataStr))
            return -1;

        $c = $this->dataStr[$pos];

        if (self::isDigitat($this->dataStr, $pos)) {
            return 0;
        } else if (self::isalnumat($this->dataStr, $pos)) {
            return 1;
        } else if ($this->modeHint == 3) {

            if ($pos + 1 < strlen($this->dataStr)) {
                $d = $this->dataStr[$pos + 1];
                $word = (ord($c) << 8) | ord($d);
                if (($word >= 0x8140 && $word <= 0x9ffc) || ($word >= 0xe040 && $word <= 0xebbf)) {
                    return 3;
                }
            }
        }

        return 2;
    }


    private function eatNum()
    {
        $ln = qr_Spec::lengthIndicator(0, $this->input->getVersion());

        $p = 0;
        while (self::isDigitat($this->dataStr, $p)) {
            $p++;
        }

        $run = $p;
        $mode = $this->identifyMode($p);

        if ($mode == 2) {
            $dif = qr_Input::estimateBitsModeNum($run) + 4 + $ln
                + qr_Input::estimateBitsMode8(1)         // + 4 + l8
                - qr_Input::estimateBitsMode8($run + 1); // - 4 - l8
            if ($dif > 0) {
                return $this->eat8();
            }
        }
        if ($mode == 1) {
            $dif = qr_Input::estimateBitsModeNum($run) + 4 + $ln
                + qr_Input::estimateBitsModeAn(1)        // + 4 + la
                - qr_Input::estimateBitsModeAn($run + 1);// - 4 - la
            if ($dif > 0) {
                return $this->eatAn();
            }
        }

        $ret = $this->input->append(0, $run, str_split($this->dataStr));
        if ($ret < 0)
            return -1;

        return $run;
    }


    private function eatAn()
    {
        $la = qr_Spec::lengthIndicator(1, $this->input->getVersion());
        $ln = qr_Spec::lengthIndicator(0, $this->input->getVersion());
        $run = 0;
        while (self::isalnumat($this->dataStr, $run)) {
            if (self::isDigitat($this->dataStr, $run)) {
                $q = $run;
                while (self::isDigitat($this->dataStr, $q)) {
                    $q++;
                }
                $dif = qr_Input::estimateBitsModeAn($run) // + 4 + la
                    + qr_Input::estimateBitsModeNum($q - $run) + 4 + $ln
                    - qr_Input::estimateBitsModeAn($q); // - 4 - la

                if ($dif < 0) {
                    break;
                } else {
                    $run = $q;
                }
            } else {
                $run++;
            }
        }
        if (!self::isalnumat($this->dataStr, $run)) {
            $dif = qr_Input::estimateBitsModeAn($run) + 4 + $la
                + qr_Input::estimateBitsMode8(1) // + 4 + l8
                - qr_Input::estimateBitsMode8($run + 1); // - 4 - l8
            if ($dif > 0) {
                return $this->eat8();
            }
        }
        $ret = $this->input->append(1, $run, str_split($this->dataStr));
        return $ret < 0 ? -1 : $run;
    }


    private function eatKanji()
    {
        $p = 0;
        while ($this->identifyMode($p) == 3) {
            $p += 2;
        }
        $ret = $this->input->append(3, $p, str_split($this->dataStr));
        return $ret < 0 ? -1 : $ret;
    }


    private function eat8()
    {
        $la = qr_Spec::lengthIndicator(1, $this->input->getVersion());
        $ln = qr_Spec::lengthIndicator(0, $this->input->getVersion());

        $p = 1;
        $dataStrLen = strlen($this->dataStr);

        while ($p < $dataStrLen) {

            $mode = $this->identifyMode($p);
            if ($mode == 3) {
                break;
            }
            if ($mode == 0) {
                $q = $p;
                while (self::isDigitat($this->dataStr, $q)) {
                    $q++;
                }
                $dif = qr_Input::estimateBitsMode8($p) // + 4 + l8
                    + qr_Input::estimateBitsModeNum($q - $p) + 4 + $ln
                    - qr_Input::estimateBitsMode8($q); // - 4 - l8
                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else if ($mode == 1) {
                $q = $p;
                while (self::isalnumat($this->dataStr, $q)) {
                    $q++;
                }
                $dif = qr_Input::estimateBitsMode8($p)  // + 4 + l8
                    + qr_Input::estimateBitsModeAn($q - $p) + 4 + $la
                    - qr_Input::estimateBitsMode8($q); // - 4 - l8
                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else {
                $p++;
            }
        }

        $run = $p;
        $ret = $this->input->append(2, $run, str_split($this->dataStr));

        if ($ret < 0)
            return -1;

        return $run;
    }


    public function splitString()
    {
        while (strlen($this->dataStr) > 0) {
            if ($this->dataStr == '')
                return 0;

            $mode = $this->identifyMode(0);

            switch ($mode) {
                case 0:
                    $length = $this->eatNum();
                    break;
                case 1:
                    $length = $this->eatAn();
                    break;
                case 3:
                    if ($mode == 3)
                        $length = $this->eatKanji();
                    else    $length = $this->eat8();
                    break;
                default:
                    $length = $this->eat8();
                    break;

            }

            if ($length == 0) return 0;
            if ($length < 0) return -1;
            $this->dataStr = substr($this->dataStr, $length);
        }
        return 1;
    }


    public function toUpper()
    {
        $stringLen = strlen($this->dataStr);
        $p = 0;

        while ($p < $stringLen) {
            $mode = self::identifyMode(substr($this->dataStr, $p, $this->modeHint));
            if ($mode == 3) {
                $p += 2;
            } else {
                if (ord($this->dataStr[$p]) >= ord('a') && ord($this->dataStr[$p]) <= ord('z')) {
                    $this->dataStr[$p] = chr(ord($this->dataStr[$p]) - 32);
                }
                $p++;
            }
        }

        return $this->dataStr;
    }


}
