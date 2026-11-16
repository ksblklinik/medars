<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Service;

/**
 * Pure-PHP LZ-String URI-encoded compressor/decompressor for BPJS VClaim/PCare v2.
 */
final class LzString
{
    private const KEY_STR_URI_SAFE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-$';

    public static function compressToEncodedURIComponent(string $input): string
    {
        if ($input === '') {
            return '';
        }

        $chars = self::utf8ToCodeUnits($input);
        return self::compress($chars, 6, static fn (int $a): string => self::KEY_STR_URI_SAFE[$a]);
    }

    public static function decompressFromEncodedURIComponent(string $input): string
    {
        if ($input === '') {
            return '';
        }

        $input = str_replace(' ', '+', $input);
        $reverseMap = array_flip(str_split(self::KEY_STR_URI_SAFE));

        return self::decompress(
            strlen($input),
            32,
            static fn (int $index): int => $reverseMap[$input[$index]] ?? 0
        );
    }

    /**
     * @param list<int> $chars
     */
    private static function compress(array $chars, int $bitsPerChar, callable $getCharFromInt): string
    {
        $dictionary = [];
        $dictionaryToCreate = [];
        $c = '';
        $wc = '';
        $w = '';
        $enlargeIn = 2;
        $dictSize = 3;
        $numBits = 2;
        $data = [];
        $dataVal = 0;
        $dataPosition = 0;

        $writeBit = static function (int $bit) use (&$dataVal, &$dataPosition, &$data, $bitsPerChar, $getCharFromInt): void {
            $dataVal = ($dataVal << 1) | ($bit & 1);
            if ($dataPosition === $bitsPerChar - 1) {
                $dataPosition = 0;
                $data[] = $getCharFromInt($dataVal);
                $dataVal = 0;
            } else {
                $dataPosition++;
            }
        };

        $writeBits = static function (int $value, int $count) use ($writeBit): void {
            for ($i = 0; $i < $count; $i++) {
                $writeBit($value & 1);
                $value >>= 1;
            }
        };

        foreach ($chars as $codeUnit) {
            $c = self::chr16($codeUnit);
            if (!array_key_exists($c, $dictionary)) {
                $dictionary[$c] = $dictSize++;
                $dictionaryToCreate[$c] = true;
            }

            $wc = $w . $c;
            if (array_key_exists($wc, $dictionary)) {
                $w = $wc;
            } else {
                if (array_key_exists($w, $dictionaryToCreate)) {
                    $firstCode = self::ord16($w);
                    if ($firstCode < 256) {
                        $writeBits(0, $numBits);
                        $writeBits($firstCode, 8);
                    } else {
                        $writeBits(1, $numBits);
                        $writeBits($firstCode, 16);
                    }
                    $enlargeIn--;
                    if ($enlargeIn === 0) {
                        $enlargeIn = 1 << $numBits;
                        $numBits++;
                    }
                    unset($dictionaryToCreate[$w]);
                } else {
                    $writeBits($dictionary[$w], $numBits);
                }

                $enlargeIn--;
                if ($enlargeIn === 0) {
                    $enlargeIn = 1 << $numBits;
                    $numBits++;
                }

                $dictionary[$wc] = $dictSize++;
                $w = $c;
            }
        }

        if ($w !== '') {
            if (array_key_exists($w, $dictionaryToCreate)) {
                $firstCode = self::ord16($w);
                if ($firstCode < 256) {
                    $writeBits(0, $numBits);
                    $writeBits($firstCode, 8);
                } else {
                    $writeBits(1, $numBits);
                    $writeBits($firstCode, 16);
                }
                $enlargeIn--;
                if ($enlargeIn === 0) {
                    $enlargeIn = 1 << $numBits;
                    $numBits++;
                }
                unset($dictionaryToCreate[$w]);
            } else {
                $writeBits($dictionary[$w], $numBits);
            }
            $enlargeIn--;
            if ($enlargeIn === 0) {
                $enlargeIn = 1 << $numBits;
                $numBits++;
            }
        }

        $writeBits(2, $numBits);

        while (true) {
            $dataVal <<= 1;
            if ($dataPosition === $bitsPerChar - 1) {
                $data[] = $getCharFromInt($dataVal);
                break;
            }
            $dataPosition++;
        }

        return implode('', $data);
    }

    private static function decompress(int $length, int $resetValue, callable $getNextValue): string
    {
        $dictionary = [0 => '0', 1 => '1', 2 => '2'];
        $enlargeIn = 4;
        $dictSize = 4;
        $numBits = 3;
        $entry = '';
        $result = [];

        $dataVal = $getNextValue(0);
        $dataPosition = $resetValue;
        $dataIndex = 1;

        $readBits = static function (int $maxPower) use (&$dataVal, &$dataPosition, &$dataIndex, $length, $resetValue, $getNextValue): int {
            $bits = 0;
            $power = 1;
            while ($power !== $maxPower) {
                $resb = $dataVal & $dataPosition;
                $dataPosition >>= 1;
                if ($dataPosition === 0) {
                    $dataPosition = $resetValue;
                    $dataVal = $dataIndex < $length ? $getNextValue($dataIndex++) : 0;
                }
                $bits |= ($resb > 0 ? 1 : 0) * $power;
                $power <<= 1;
            }
            return $bits;
        };

        $next = $readBits(4);
        if ($next === 0) {
            $c = self::chr16($readBits(256));
        } elseif ($next === 1) {
            $c = self::chr16($readBits(65536));
        } elseif ($next === 2) {
            return '';
        } else {
            return '';
        }

        $dictionary[3] = $c;
        $w = $c;
        $result[] = $c;

        while (true) {
            if ($dataIndex > $length) {
                return '';
            }

            $cc = $readBits(1 << $numBits);
            if ($cc === 0) {
                $dictionary[$dictSize++] = self::chr16($readBits(256));
                $cc = $dictSize - 1;
                $enlargeIn--;
            } elseif ($cc === 1) {
                $dictionary[$dictSize++] = self::chr16($readBits(65536));
                $cc = $dictSize - 1;
                $enlargeIn--;
            } elseif ($cc === 2) {
                return self::utf16UnitsToUtf8(implode('', $result));
            }

            if ($enlargeIn === 0) {
                $enlargeIn = 1 << $numBits;
                $numBits++;
            }

            if (array_key_exists($cc, $dictionary)) {
                $entry = $dictionary[$cc];
            } elseif ($cc === $dictSize) {
                $entry = $w . self::firstChar16($w);
            } else {
                return '';
            }

            $result[] = $entry;
            $dictionary[$dictSize++] = $w . self::firstChar16($entry);
            $enlargeIn--;

            if ($enlargeIn === 0) {
                $enlargeIn = 1 << $numBits;
                $numBits++;
            }

            $w = $entry;
        }
    }

    /**
     * @return list<int>
     */
    private static function utf8ToCodeUnits(string $utf8): array
    {
        $utf16 = mb_convert_encoding($utf8, 'UTF-16BE', 'UTF-8');
        $units = [];
        $len = strlen($utf16);
        for ($i = 0; $i < $len; $i += 2) {
            $units[] = (ord($utf16[$i]) << 8) | ord($utf16[$i + 1]);
        }
        return $units;
    }

    private static function chr16(int $codeUnit): string
    {
        return chr(($codeUnit >> 8) & 0xFF) . chr($codeUnit & 0xFF);
    }

    private static function ord16(string $twoBytes): int
    {
        return (ord($twoBytes[0]) << 8) | ord($twoBytes[1]);
    }

    private static function firstChar16(string $str): string
    {
        return substr($str, 0, 2);
    }

    private static function utf16UnitsToUtf8(string $utf16be): string
    {
        return (string) mb_convert_encoding($utf16be, 'UTF-8', 'UTF-16BE');
    }
}
