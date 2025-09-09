<?php
namespace YOCLIB\Multiformats\Multibase;

class Base45{

    /**
     * @param string $binary
     * @param string $alphabet
     * @return string
     */
    public static function decode(string $binary,string $alphabet): string{
        $buffer = self::getIndexes(strtoupper($binary),$alphabet);
        $data = '';
        for ($i = 0; $i < count($buffer); $i += 3) {
            if (count($buffer) - $i >= 3) {
                $x = $buffer[$i] + $buffer[$i + 1] * 45 + $buffer[$i + 2] * 45 * 45;
                list($a, $b) = self::divmod($x, 256);
                $data .= sprintf('%s%s', chr((int)$a), chr((int)$b));
            } else {
                $x = $buffer[$i] + $buffer[$i + 1] * 45;
                $data .= chr((int)$x);
            }
        }
        return $data;
    }

    /**
     * @param string $data
     * @param string $alphabet
     * @return string
     */
    public static function encode(string $data,string $alphabet): string{
        $binary = '';
        $buffer = str_split($data);
        for ($i = 0; $i < count($buffer); $i += 2) {
            if (count($buffer) - $i > 1) {
                $x = (ord($buffer[$i]) << 8) + ord($buffer[$i + 1]);
                list($e, $rest) = self::divmod($x, 45 * 45);
                list($d, $c) = self::divmod($rest, 45);
                $binary .= sprintf('%s%s%s', @$alphabet[$c], @$alphabet[$d], @$alphabet[$e]);
            } else {
                list($d, $c) = self::divmod(ord($buffer[$i]), 45);
                $binary .= sprintf('%s%s', @$alphabet[$c], @$alphabet[$d]);
            }
        }

        return $binary;
    }

    private static function divmod(int $x, int $y): array{
        $resX = floor($x / $y);
        $resY = $x % $y;

        return [$resX, $resY];
    }

    private static function getIndexes(string $input,string $alphabet): ?array{
        $indexes = [];
        for ($i = 0; $i < strlen($input); $i++) {
            $position = strpos($alphabet, $input[$i]);
            if ($position === false) {
                return null;
            }
            $indexes[] = $position;
        }
        return $indexes;
    }

}