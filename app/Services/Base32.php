<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * RFC 4648 Base32 (alphabet A-Z2-7), without "=" padding,
 * which is the format authenticator apps expect for TOTP secrets.
 */
final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            $output .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $output;
    }

    public static function decode(string $base32): string
    {
        $base32 = strtoupper(str_replace([' ', '='], '', $base32));

        $bits = '';
        foreach (str_split($base32) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                throw new InvalidArgumentException('Invalid Base32 character.');
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }

        return $output;
    }
}
