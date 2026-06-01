<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\Signature;

final class SignatureTest extends TestCase
{
    private function signature(): Signature
    {
        return new Signature('123456', 'super-secret');
    }

    public function test_token_follows_the_documented_double_hmac_scheme(): void
    {
        $data = '123456ORDER_1100.50https://shop.tj/callback';

        // Reference implementation: HMAC(data, HMAC(password, terminalId)).
        $secret = hash_hmac('sha256', 'super-secret', '123456');
        $expected = hash_hmac('sha256', $data, $secret);

        $this->assertSame($expected, $this->signature()->generate($data));
    }

    public function test_token_is_deterministic_and_64_hex_chars(): void
    {
        $sig = $this->signature();

        $token = $sig->generate('payload');

        $this->assertSame($token, $sig->generate('payload'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function test_verify_accepts_a_valid_token_and_rejects_a_forgery(): void
    {
        $sig = $this->signature();
        $token = $sig->generate('payload');

        $this->assertTrue($sig->verify('payload', $token));
        $this->assertFalse($sig->verify('payload', 'deadbeef'));
        $this->assertFalse($sig->verify('tampered', $token));
    }

    public function test_concat_skips_nulls_and_stringifies_numbers(): void
    {
        $this->assertSame('123456ORDER100.50', Signature::concat('123456', 'ORDER', null, '100.50'));
        $this->assertSame('a10b', Signature::concat('a', 10, 'b'));
    }
}
