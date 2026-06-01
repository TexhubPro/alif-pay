<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\AlifPay;
use TexHub\AlifPay\Config;
use TexHub\AlifPay\Requests\MarketplaceRequest;
use TexHub\AlifPay\Signature;
use TexHub\AlifPay\Tests\Support\FakeTransport;

final class MarketplaceClientTest extends TestCase
{
    private function alif(FakeTransport $transport): AlifPay
    {
        return new AlifPay(new Config('123456', 'super-secret'), $transport);
    }

    public function test_initiate_sets_marketplace_header_and_split(): void
    {
        $transport = new FakeTransport();

        $this->alif($transport)->marketplace()->initiate(
            MarketplaceRequest::make('MP_1', 500)
                ->callbackUrl('https://shop.tj/cb')
                ->returnUrl('https://shop.tj/ok')
                ->splitTo('partner_1', 300)
                ->splitTo('partner_2', 200)
        );

        $this->assertSame('true', $transport->lastHeaders['isMarketPlace']);
        $this->assertCount(2, $transport->lastBody['mp_terminal_amounts']);

        $expectedToken = (new Signature('123456', 'super-secret'))
            ->generate('123456MP_1500.00https://shop.tj/cb');
        $this->assertSame($expectedToken, $transport->lastBody['token']);
    }

    public function test_confirm_delivery_signs_transaction_and_amount(): void
    {
        $transport = (new FakeTransport())->willReturnJson(['code' => 200, 'message' => 'Успешно']);

        $this->alif($transport)->marketplace()->confirmDelivery('789013', 300);

        $this->assertSame('https://test-web.alif.tj/confirm-delivery', $transport->lastUrl);
        $this->assertSame('300.00', $transport->lastBody['amount']);

        $expectedToken = (new Signature('123456', 'super-secret'))->generate('123456789013300.00');
        $this->assertSame($expectedToken, $transport->lastBody['token']);
    }

    public function test_confirm_vsa_mcr_delivery_signs_parent_transaction(): void
    {
        $transport = (new FakeTransport())->willReturnJson(['code' => 200, 'message' => 'Успешно']);

        $this->alif($transport)->marketplace()->confirmVsaMcrDelivery('parent_99');

        $this->assertSame('https://test-web.alif.tj/confirm-vsa-and-mcr-delivery', $transport->lastUrl);

        $expectedToken = (new Signature('123456', 'super-secret'))->generate('123456parent_99');
        $this->assertSame($expectedToken, $transport->lastBody['token']);
    }
}
