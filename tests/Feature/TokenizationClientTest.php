<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\AlifPay;
use TexHub\AlifPay\Config;
use TexHub\AlifPay\Enums\TokenizationGate;
use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Requests\TokenChargeRequest;
use TexHub\AlifPay\Requests\TokenizationRequest;
use TexHub\AlifPay\Signature;
use TexHub\AlifPay\Tests\Support\FakeTransport;

final class TokenizationClientTest extends TestCase
{
    private function alif(FakeTransport $transport): AlifPay
    {
        return new AlifPay(new Config('123456', 'super-secret'), $transport);
    }

    private function signature(): Signature
    {
        return new Signature('123456', 'super-secret');
    }

    public function test_initiate_wraps_payload_in_data_and_signs_phone_and_gate(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'code' => 200,
            'message' => 'Успешно',
            'url' => 'https://web.alif.tj/#tokenize?session=xyz',
        ]);

        $response = $this->alif($transport)->tokenization()->initiate(
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

        $expectedToken = $this->signature()->generate('123456+992900123456tokenization_wallet');
        $this->assertSame($expectedToken, $data['token']);

        $this->assertSame('https://web.alif.tj/#tokenize?session=xyz', $response->redirectUrl());
    }

    public function test_a_phone_written_any_other_way_is_signed_in_the_documented_form(): void
    {
        $transport = new FakeTransport();

        $this->alif($transport)->tokenization()->initiate(
            TokenizationRequest::make('ORDER_2', '900 123-456', TokenizationGate::KortiMilli)
                ->callbackUrl('https://shop.tj/cb')
                ->returnUrl('https://shop.tj/ok')
        );

        $data = $transport->lastBody['data'];

        $this->assertSame('+992900123456', $data['phone']);
        $this->assertSame(
            $this->signature()->generate('123456+992900123456tokenization_korti_milli'),
            $data['token'],
        );
    }

    public function test_a_number_that_is_not_a_tajik_phone_is_refused(): void
    {
        $this->expectException(ConfigurationException::class);

        TokenizationRequest::make('ORDER_3', '+7 999 123-45-67', TokenizationGate::Wallet);
    }

    public function test_charge_signs_the_bound_token_in_the_hash_header(): void
    {
        $transport = (new FakeTransport())->willReturnJson(['code' => 200, 'message' => 'Успешно']);

        $this->alif($transport)->tokenization()->charge(
            TokenChargeRequest::make('ORDER_123456', '100.00', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890')
                ->callbackUrl('https://shop.tj/cb')
                ->email('user@example.com')
        );

        $this->assertSame('https://test-web.alif.tj/pay/token', $transport->lastUrl);
        $this->assertSame(100.00, $transport->lastBody['amount']);
        $this->assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $transport->lastBody['token']);
        $this->assertSame('123456', $transport->lastBody['key']);
        $this->assertArrayNotHasKey('token', $transport->lastHeaders);

        $expected = $this->signature()
            ->generate('123456ORDER_123456100.00a1b2c3d4-e5f6-7890-abcd-ef1234567890');
        $this->assertSame($expected, $transport->lastHeaders['hash']);
    }

    public function test_charge_can_split_across_partner_terminals(): void
    {
        $transport = (new FakeTransport())->willReturnJson(['code' => 200, 'message' => 'Успешно']);

        $this->alif($transport)->tokenization()->charge(
            TokenChargeRequest::make('ORDER_1', '100.00', 'token-uuid')
                ->callbackUrl('https://shop.tj/cb')
                ->splitTo('TERM_001', '70.00')
                ->splitTo('TERM_002', '30.00')
        );

        $this->assertCount(2, $transport->lastBody['mpTerminalInfo']);
        $this->assertSame(70.00, $transport->lastBody['mpTerminalInfo'][0]['amount']);
    }

    public function test_charge_splits_must_add_up_to_the_charged_amount(): void
    {
        $this->expectException(ConfigurationException::class);

        TokenChargeRequest::make('ORDER_1', '100.00', 'token-uuid')
            ->callbackUrl('https://shop.tj/cb')
            ->splitTo('TERM_001', '70.00')
            ->toArray();
    }

    public function test_status_signs_the_request_id(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'code' => 200,
            'message' => 'Успешно',
            'payload' => [
                'transactionId' => 12345,
                'token' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
                'account' => '9929****506',
                'status' => 'approved',
                'transaction_type' => 'tokenization_wallet',
            ],
        ]);

        $response = $this->alif($transport)->tokenization()->status('ORDER_123456');

        $this->assertSame('https://test-web.alif.tj/tokenization/status', $transport->lastUrl);
        $this->assertSame('ORDER_123456', $transport->lastBody['request_id']);
        $this->assertSame('', $transport->lastBody['token']);
        $this->assertSame(
            $this->signature()->generate('123456ORDER_123456'),
            $transport->lastHeaders['hash'],
        );
        $this->assertSame('approved', $response->get('payload')['status']);
    }

    public function test_remove_signs_the_token_not_the_request_id(): void
    {
        $transport = (new FakeTransport())->willReturnJson(['code' => 200, 'message' => 'Успешно']);

        $this->alif($transport)->tokenization()->remove('21321', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890');

        $this->assertSame('https://test-web.alif.tj/tokenization/remove', $transport->lastUrl);
        $this->assertSame('21321', $transport->lastBody['request_id']);
        $this->assertSame(
            $this->signature()->generate('123456a1b2c3d4-e5f6-7890-abcd-ef1234567890'),
            $transport->lastHeaders['hash'],
        );
    }

    public function test_remove_without_a_token_never_reaches_the_gateway(): void
    {
        $transport = new FakeTransport();

        $this->expectException(ConfigurationException::class);

        try {
            $this->alif($transport)->tokenization()->remove('21321', '');
        } finally {
            $this->assertSame(0, $transport->calls);
        }
    }
}
