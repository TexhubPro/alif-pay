<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Requests\Amount;

final class AmountTest extends TestCase
{
    public function test_it_normalizes_to_two_decimals(): void
    {
        $this->assertSame('100.50', Amount::of(100.5)->value);
        $this->assertSame('100.00', Amount::of(100)->value);
        $this->assertSame('1500.00', Amount::of('1500')->value);
        $this->assertSame('99.99', Amount::of('99.99')->value);
        $this->assertSame('100.50', (string) Amount::of('100.50'));
    }

    public function test_it_rejects_non_positive_amounts(): void
    {
        $this->expectException(ConfigurationException::class);
        Amount::of(0);
    }

    public function test_it_rejects_non_numeric_strings(): void
    {
        $this->expectException(ConfigurationException::class);
        Amount::of('abc');
    }
}
