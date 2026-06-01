<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\AlifPay;
use TexHub\AlifPay\Config;
use TexHub\AlifPay\Enums\TokenizationGate;
use TexHub\AlifPay\Requests\TokenizationRequest;
use TexHub\AlifPay\Signature;
use TexHub\AlifPay\Tests\Support\FakeTransport;

final class TokenizationClientTest extends TestCase
{
    public function test_initiate_wraps_payload_in_data_and_signs_phone_and_gate(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'code' => 200,
            'message' => 'Успешно',
            'url' => 'https://web.alif.tj/#tokenize?session=xyz',
        ]);

        $alif = new AlifPay(new Config('123456', 'super-secret'), $transport);

        $response = $alif->tokenization()->initiate(
            TokenizationRequest::make('ORDER_1', '+992900123456', TokenizationGate::Wallet)
                ->callbackUrl('https://shop.tj/cb')
                ->returnUrl('https://shop.tj/ok')
                ->clientId('client_42')
        );

        $this->assertArrayHasKey('data', $transport->lastBody);
        $data = $transport->lastBody['data'];

        $this->assertSame('123456', $data['key']);
        $this->assertSame('tokenization_wallet', $data['gate']);
        $this->assertSame('client_42', $data['clientID']);
        $this->assertSame('tokenization_wallet', $transport->lastHeaders['gate']);

        $expectedToken = (new Signature('123456', 'super-secret'))
            ->generate('123456+992900123456tokenization_wallet');
        $this->assertSame($expectedToken, $data['token']);

        $this->assertSame('https://web.alif.tj/#tokenize?session=xyz', $response->redirectUrl());
    }
}
