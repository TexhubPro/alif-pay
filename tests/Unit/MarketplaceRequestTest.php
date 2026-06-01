<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Requests\MarketplaceRequest;

final class MarketplaceRequestTest extends TestCase
{
    public function test_it_builds_split_payment_body(): void
    {
        $body = MarketplaceRequest::make('MP_1', 500)
            ->callbackUrl('https://shop.tj/cb')
            ->returnUrl('https://shop.tj/ok')
            ->splitTo('partner_1', 300)
            ->splitTo('partner_2', 200)
            ->toArray();

        $this->assertSame('500.00', $body['amount']);
        $this->assertCount(2, $body['mp_terminal_amounts']);
        $this->assertSame('partner_1', $body['mp_terminal_amounts'][0]['terminal_id']);
        $this->assertSame('300.00', $body['mp_terminal_amounts'][0]['amount']);
    }

    public function test_it_rejects_when_split_does_not_match_total(): void
    {
        $this->expectException(ConfigurationException::class);

        MarketplaceRequest::make('MP_2', 500)
            ->callbackUrl('https://shop.tj/cb')
            ->returnUrl('https://shop.tj/ok')
            ->splitTo('partner_1', 300)
            ->toArray();
    }

    public function test_it_requires_at_least_one_terminal(): void
    {
        $this->expectException(ConfigurationException::class);

        MarketplaceRequest::make('MP_3', 100)
            ->callbackUrl('https://shop.tj/cb')
            ->returnUrl('https://shop.tj/ok')
            ->toArray();
    }
}
