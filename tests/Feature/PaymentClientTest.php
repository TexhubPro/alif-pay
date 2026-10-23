<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\AlifPay;
use TexHub\AlifPay\Config;
use TexHub\AlifPay\Enums\Environment;
use TexHub\AlifPay\Enums\Gate;
use TexHub\AlifPay\Exceptions\ApiException;
use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Requests\PaymentRequest;
use TexHub\AlifPay\Signature;
use TexHub\AlifPay\Tests\Support\FakeTransport;

final class PaymentClientTest extends TestCase
{
    private function alif(FakeTransport $transport): AlifPay
    {
        $config = new Config(
            terminalId: '123456',
            terminalPassword: 'super-secret',
            environment: Environment::Test,
        );

        return new AlifPay($config, $transport);
    }

    public function test_initiate_sends_signed_request_and_returns_redirect_url(): void
    {
        $transport = new FakeTransport();
        $alif = $this->alif($transport);

        $response = $alif->payments()->initiate(
            PaymentRequest::make('ORDER_1', 100.5)
                ->gate(Gate::KortiMilli)
                ->callbackUrl('https://shop.tj/cb')
                ->returnUrl('https://shop.tj/ok')
        );

        $this->assertSame('https://test-web.alif.tj/v2/', $transport->lastUrl);
        $this->assertSame('korti_milli', $transport->lastHeaders['gate']);

        $this->assertSame('123456', $transport->lastBody['key']);

        $expectedSignature = new Signature('123456', 'super-secret');
        $expectedToken = $expectedSignature->generate('123456ORDER_1100.50https://shop.tj/cb');
        $this->assertSame($expectedToken, $transport->lastBody['token']);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('https://web.alif.tj/abc123', $response->redirectUrl());
    }

    public function test_a_legacy_gate_value_can_still_be_sent(): void
    {
        $transport = new FakeTransport();

        $this->alif($transport)->payments()->initiate(
            PaymentRequest::make('ORDER_1', 10)
                ->gateValue('vsa')
                ->callbackUrl('https://shop.tj/cb')
                ->returnUrl('https://shop.tj/ok')
        );

        $this->assertSame('vsa', $transport->lastHeaders['gate']);
        $this->assertSame('vsa', $transport->lastBody['gate']);
    }

    public function test_visa_and_mastercard_go_out_under_their_documented_names(): void
    {
        $transport = new FakeTransport();

        $this->alif($transport)->payments()->initiate(
            PaymentRequest::make('ORDER_INT_001', 500)
                ->gate(Gate::Mastercard)
                ->email('buyer@example.com')
                ->callbackUrl('https://shop.tj/cb')
                ->returnUrl('https://shop.tj/ok')
        );

        $this->assertSame('mastercard', $transport->lastHeaders['gate']);
        $this->assertSame('mastercard', $transport->lastBody['gate']);
    }

    public function test_info_with_tax_details_is_sent_with_its_signature(): void
    {
        $transport = new FakeTransport();

        $this->alif($transport)->payments()->initiate(
            PaymentRequest::make('ORDER_1', 10)
                ->gate(Gate::KortiMilli)
                ->info('ИНН 626409237')
                ->signInfo()
                ->callbackUrl('https://shop.tj/cb')
                ->returnUrl('https://shop.tj/ok')
        );

        $expected = (new Signature('123456', 'super-secret'))->generate('ИНН 626409237');
        $this->assertSame($expected, $transport->lastBody['info_hash']);
        $this->assertSame('ИНН 626409237', $transport->lastBody['info']);
    }

    public function test_info_hash_is_not_sent_unless_asked_for(): void
    {
        $transport = new FakeTransport();

        $this->alif($transport)->payments()->initiate(
            PaymentRequest::make('ORDER_1', 10)
                ->info('Оплата заказа')
                ->callbackUrl('https://shop.tj/cb')
                ->returnUrl('https://shop.tj/ok')
        );

        $this->assertArrayNotHasKey('info_hash', $transport->lastBody);
    }

    public function test_info_hash_is_refused_outside_korti_milli(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->alif(new FakeTransport())->payments()->initiate(
            PaymentRequest::make('ORDER_1', 10)
                ->gate(Gate::Wallet)
                ->info('ИНН 626409237')
                ->signInfo()
                ->callbackUrl('https://shop.tj/cb')
                ->returnUrl('https://shop.tj/ok')
        );
    }

    public function test_a_gate_that_is_not_a_gate_is_refused(): void
    {
        $this->expectException(ConfigurationException::class);

        PaymentRequest::make('ORDER_1', 10)->gateValue('../../etc/passwd');
    }

    public function test_non_success_code_throws_api_exception(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'code' => 401,
            'message' => 'Ошибка авторизации',
            'url' => '',
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Ошибка авторизации');

        $this->alif($transport)->payments()->initiate(
            PaymentRequest::make('ORDER_1', 10)
                ->callbackUrl('https://shop.tj/cb')
                ->returnUrl('https://shop.tj/ok')
        );
    }

    public function test_duplicate_exception_is_flagged(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'code' => 208,
            'message' => 'Дубликат',
            'url' => '',
        ], 208);

        try {
            $this->alif($transport)->payments()->initiate(
                PaymentRequest::make('ORDER_1', 10)
                    ->callbackUrl('https://shop.tj/cb')
                    ->returnUrl('https://shop.tj/ok')
            );
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertTrue($e->isDuplicate());
            $this->assertSame(208, $e->apiCode);
        }
    }

    public function test_check_status_posts_to_checktxn(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'orderId' => 'ORDER_1',
            'transactionId' => '789012',
            'status' => 'ok',
            'token' => 'a'.str_repeat('b', 63),
            'amount' => 150.50,
            'phone' => '992901234567',
        ]);

        $response = $this->alif($transport)->payments()->checkStatus('ORDER_1');

        $this->assertSame('https://test-web.alif.tj/checktxn', $transport->lastUrl);
        $this->assertSame('ORDER_1', $transport->lastBody['orderId']);
        $this->assertSame('ok', $response->get('status'));
        $this->assertSame('789012', $response->get('transactionId'));

        $expected = (new Signature('123456', 'super-secret'))->generate('123456ORDER_1');
        $this->assertSame($expected, $transport->lastBody['token']);
    }

    public function test_check_status_still_raises_the_errors_the_gateway_reports(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'code' => 404,
            'message' => 'Транзакция не найдена',
        ], 404);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Транзакция не найдена');

        $this->alif($transport)->payments()->checkStatus('ORDER_1');
    }

    public function test_currency_lookup_signs_the_order_id_and_reads_the_rates(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'code' => 200,
            'message' => 'Успешно',
            'payload' => [
                'type' => 'USD',
                'rate' => 10.95,
                'rates' => [
                    ['type' => 'TJS', 'rate' => 1],
                    ['type' => 'EUR', 'rate' => 11.80],
                ],
            ],
        ]);

        $rates = $this->alif($transport)->payments()->currency('ORDER_123456');

        $this->assertSame('https://test-web.alif.tj/currency', $transport->lastUrl);
        $this->assertSame('ORDER_123456', $transport->lastBody['order_id']);
        $this->assertSame((new Signature('123456', 'super-secret'))->generate('123456ORDER_123456'), $transport->lastBody['token']);
        $this->assertSame('USD', $rates->currency);
        $this->assertSame(10.95, $rates->rateFor('USD'));
        $this->assertSame(11.8, $rates->rateFor('EUR'));
        $this->assertNull($rates->rateFor('GBP'));
        $this->assertSame('1100.48', $rates->toSomoni('100.50'));
    }
}
