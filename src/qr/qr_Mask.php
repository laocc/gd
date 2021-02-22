<?php


namespace esp\gd\qr;

class qr_Mask
{
    private function writeFormatInformation($width, &$frame, $mask, $level)
    {
        $blacks = 0;
        $format = qr_Spec::getFormatInfo($mask, $level);

        for ($i = 0; $i < 8; $i++) {
            if ($format & 1) {
                $blacks += 2;
                $v = 0x85;
            } else {
                $v = 0x84;
            }

            $frame[8][$width - 1 - $i] = chr($v);
            if ($i < 6) {
                $frame[$i][8] = chr($v);
            } else {
                $frame[$i + 1][8] = chr($v);
            }
            $format = $format >> 1;
        }

        for ($i = 0; $i < 7; $i++) {
            if ($format & 1) {
                $blacks += 2;
                $v = 0x85;
            } else {
                $v = 0x84;
            }

            $frame[$width - 7 + $i][8] = chr($v);
            if ($i == 0) {
                $frame[8][7] = chr($v);
            } else {
                $frame[8][6 - $i] = chr($v);
            }

            $format = $format >> 1;
        }

        return $blacks;
    }

    private static function mask($i, $x, $y)
    {
        switch ($i) {
            case 0:
                return ($x + $y) & 1;
                break;
            case 1:
                return ($y & 1);
                break;
            case 2:
                return ($x % 3);
                break;
            case 3:
                return ($x + $y) % 3;
                break;
            case 4:
                return (((int)($y / 2)) + ((int)($x / 3))) & 1;
                break;
            case 5:
                return (($x * $y) & 1) + ($x * $y) % 3;
                break;
            case 6:
                return ((($x * $y) & 1) + ($x * $y) % 3) & 1;
                break;
            case 7:
                return ((($x * $y) % 3) + (($x + $y) & 1)) & 1;
                break;
            default:
                return 0;
        }
    }


    private function generateMaskNo($maskNo, $width, $frame)
    {
        $bitMask = array_fill(0, $width, array_fill(0, $width, 0));
        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (ord($frame[$y][$x]) & 0x80) {
                    $bitMask[$y][$x] = 0;
                } else {
                    $maskFunc = self::mask($maskNo, $x, $y);
                    $bitMask[$y][$x] = ($maskFunc == 0) ? 1 : 0;
                }
            }
        }
        return $bitMask;
    }


    private function makeMaskNo($maskNo, $width, $s, &$d)
    {
        $bitMask = $this->generateMaskNo($maskNo, $width, $s);
        $d = $s;
        $b = 0;
        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($bitMask[$y][$x] == 1) {
                    $d[$y][$x] = chr(ord($s[$y][$x]) ^ (int)$bitMask[$y][$x]);
                }
                $b += (int)(ord($d[$y][$x]) & 1);
            }
        }
        return $b;
    }


    public function makeMask($width, $frame, $maskNo, $level)
    {
        $masked = array_fill(0, $width, str_repeat("\0", $width));
        $this->makeMaskNo($maskNo, $width, $frame, $masked);
        $this->writeFormatInformation($width, $masked, $maskNo, $level);
        return $masked;
    }


}
