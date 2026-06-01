<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\AlifPay;
use TexHub\AlifPay\Config;
use TexHub\AlifPay\Enums\Environment;
use TexHub\AlifPay\Enums\Gate;
use TexHub\AlifPay\Exceptions\ApiException;
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

        // URL + headers
        $this->assertSame('https://test-web.alif.tj/v2/', $transport->lastUrl);
        $this->assertSame('korti_milli', $transport->lastHeaders['gate']);

        // Body carries terminal key and the correct HMAC token.
        $this->assertSame('123456', $transport->lastBody['key']);

        $expectedSignature = new Signature('123456', 'super-secret');
        $expectedToken = $expectedSignature->generate('123456ORDER_1100.50https://shop.tj/cb');
        $this->assertSame($expectedToken, $transport->lastBody['token']);

        // Response
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('https://web.alif.tj/abc123', $response->redirectUrl());
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
            'code' => 200,
            'message' => 'Успешно',
            'status' => 'ok',
        ]);

        $this->alif($transport)->payments()->checkStatus('ORDER_1');

        $this->assertSame('https://test-web.alif.tj/checktxn', $transport->lastUrl);
        $this->assertSame('ORDER_1', $transport->lastBody['order_id']);
    }
}
