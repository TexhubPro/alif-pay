<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\AlifPay;
use TexHub\AlifPay\Config;
use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Requests\DeliveryConfirmation;
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
        $this->assertCount(2, $transport->lastBody['mpTerminalInfo']);

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

    public function test_confirm_vsa_mcr_delivery_sends_every_child_transaction(): void
    {
        $transport = (new FakeTransport())->willReturnJson(['code' => 200, 'message' => 'Успешно']);

        $this->alif($transport)->marketplace()->confirmVsaMcrDelivery(
            '100000',
            new DeliveryConfirmation('100001', 150),
            new DeliveryConfirmation('100002', '200.00'),
        );

        $this->assertSame('https://test-web.alif.tj/confirm-vsa-and-mcr-delivery', $transport->lastUrl);
        $this->assertSame('100000', $transport->lastBody['parent_transaction_id']);
        $this->assertSame(
            [
                ['transaction_id' => '100001', 'amount' => 150.00],
                ['transaction_id' => '100002', 'amount' => 200.00],
            ],
            $transport->lastBody['extra'],
        );

        $expectedToken = (new Signature('123456', 'super-secret'))->generate('123456100000');
        $this->assertSame($expectedToken, $transport->lastBody['token']);
    }

    public function test_confirm_vsa_mcr_delivery_without_children_is_refused_before_sending(): void
    {
        $transport = new FakeTransport();

        $this->expectException(ConfigurationException::class);

        try {
            $this->alif($transport)->marketplace()->confirmVsaMcrDelivery('100000');
        } finally {
            $this->assertSame(0, $transport->calls);
        }
    }

    public function test_cancel_passes_the_reason_through(): void
    {
        $transport = (new FakeTransport())->willReturnJson(['code' => 200, 'message' => 'Успешно']);

        $this->alif($transport)->marketplace()->cancel('789013', 150, 'Частичный возврат');

        $this->assertSame('https://test-web.alif.tj/cancel/marketplace', $transport->lastUrl);
        $this->assertSame('Частичный возврат', $transport->lastBody['reason']);
        $this->assertSame('150.00', $transport->lastBody['amount']);
    }

    public function test_cancel_omits_an_empty_reason(): void
    {
        $transport = (new FakeTransport())->willReturnJson(['code' => 200, 'message' => 'Успешно']);

        $this->alif($transport)->marketplace()->cancel('789013', 150);

        $this->assertArrayNotHasKey('reason', $transport->lastBody);
    }

    public function test_check_status_reads_the_transaction_with_its_sub_transactions(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'orderId' => 'MP_ORDER_123',
            'transactionId' => '789015',
            'status' => 'ok',
            'token' => str_repeat('a', 64),
            'amount' => 500.00,
            'sub_transactions' => [
                ['terminal_id' => 'SELLER_001', 'transaction_id' => '789016', 'status' => 'ok'],
            ],
        ]);

        $response = $this->alif($transport)->marketplace()->checkStatus('MP_ORDER_123');

        $this->assertSame('MP_ORDER_123', $transport->lastBody['orderId']);
        $this->assertCount(1, $response->get('sub_transactions'));
    }
}
