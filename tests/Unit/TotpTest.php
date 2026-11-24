<?php

namespace Tests\Unit;

use App\Services\Base32;
use App\Services\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    #[DataProvider('rfc6238Vectors')]
    public function test_it_generates_rfc_6238_codes(int $timestamp, string $expected): void
    {
        $totp = new Totp;
        $actual = $totp->code('12345678901234567890', $timestamp);
        $this->assertSame($expected, $actual);
    }

    public static function rfc6238Vectors(): array
    {
        return [
            'T=59' => [59, '287082'],
            'T=1111111109' => [1111111109, '081804'],
            'T=1111111111' => [1111111111, '050471'],
            'T=1234567890' => [1234567890, '005924'],
            'T=2000000000' => [2000000000, '279037'],
        ];
    }

    public function test_base32_encodes_rfc_4648_vector(): void
    {
        $base32 = Base32::encode('foobar');
        $this->assertSame('MZXW6YTBOI', $base32);
    }

    public function test_base32_decodes_the_rfc_6238_secret(): void
    {
        $decoded = Base32::decode('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
        $this->assertSame('12345678901234567890', $decoded);
    }

    public function test_generated_secret_is_32_base32_characters(): void
    {
        $totp = new Totp;
        $secret = $totp->generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
    }
}
