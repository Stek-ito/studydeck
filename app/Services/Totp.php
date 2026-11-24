<?php

namespace App\Services;

final class Totp
{
    private const PERIOD = 30;   // seconds per time step

    private const DIGITS = 6;

    /**
     * @param  string  $secret  raw binary secret (not Base32)
     * @param  int  $timestamp  Unix time in seconds
     */
    public function code(string $secret, int $timestamp): string
    {
        $counter = intdiv($timestamp, self::PERIOD);
        $binaryCounter = pack('J', $counter);

        $hash = hash_hmac('sha1', $binaryCounter, $secret, true);

        $offset = ord($hash[19]) & 0x0F;

        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }
}
