<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\Enums\PaymentStatus;
use TexHub\AlifPay\Enums\TokenizationStatus;
use TexHub\AlifPay\Exceptions\InvalidSignatureException;
use TexHub\AlifPay\Signature;
use TexHub\AlifPay\Webhook\WebhookHandler;

final class WebhookHandlerTest extends TestCase
{
    private function handler(): WebhookHandler
    {
        return new WebhookHandler(new Signature('123456', 'super-secret'));
    }

    public function test_it_parses_a_payment_callback(): void
    {
        $body = json_encode([
            'orderId' => 'ORDER_123456',
            'transactionId' => '789012',
            'status' => 'ok',
            'token' => 'abc',
            'amount' => 150.50,
            'account' => '622617******1234',
            'phone' => '992901234567',
            'transaction_type' => 'korti_milli',
        ]);

        $callback = $this->handler()->paymentCallback($body);

        $this->assertSame('ORDER_123456', $callback->orderId);
        $this->assertSame(PaymentStatus::Ok, $callback->status);
        $this->assertTrue($callback->isSuccessful());
        $this->assertFalse($callback->isMarketplace());
        $this->assertSame(150.50, $callback->amount);
    }

    public function test_it_parses_marketplace_sub_transactions(): void
    {
        $callback = $this->handler()->paymentCallback([
            'orderId' => 'MP_ORDER_1',
            'transactionId' => '789012',
            'status' => 'ok',
            'amount' => 500.0,
            'sub_transactions' => [
                ['terminal_id' => 'p1', 'transaction_id' => '789013', 'status' => 'ok'],
                ['terminal_id' => 'p2', 'transaction_id' => '789014', 'status' => 'ok'],
            ],
        ]);

        $this->assertTrue($callback->isMarketplace());
        $this->assertCount(2, $callback->subTransactions);
        $this->assertSame('p1', $callback->subTransactions[0]->terminalId);
        $this->assertSame(PaymentStatus::Ok, $callback->subTransactions[0]->status);
    }

    public function test_it_parses_a_tokenization_callback(): void
    {
        $callback = $this->handler()->tokenizationCallback([
            'code' => 1,
            'message' => 'Успешно',
            'reason_code' => 'success',
            'payload' => [
                'transactionId' => 12345,
                'orderId' => 'ORDER_123456',
                'token' => str_repeat('a', 64),
                'account' => '9929****506',
                'status' => 'approved',
                'transaction_type' => 'tokenization_wallet',
            ],
        ]);

        $this->assertSame(TokenizationStatus::Success, $callback->statusCode);
        $this->assertTrue($callback->isSuccessful());
        $this->assertSame('ORDER_123456', $callback->orderId);
        $this->assertSame(12345, $callback->transactionId);
    }

    public function test_it_verifies_a_payment_callback_token(): void
    {
        $handler = $this->handler();
        $signature = new Signature('123456', 'super-secret');

        // Default signing string: orderId . amount
        $token = $signature->generate('ORDER_1100.00');

        $callback = $handler->paymentCallback([
            'orderId' => 'ORDER_1',
            'amount' => 100.0,
            'status' => 'ok',
            'token' => $token,
        ]);

        $this->assertTrue($handler->verifyPaymentCallback($callback));

        $forged = $handler->paymentCallback([
            'orderId' => 'ORDER_1',
            'amount' => 100.0,
            'status' => 'ok',
            'token' => 'forged',
        ]);
        $this->assertFalse($handler->verifyPaymentCallback($forged));

        $this->expectException(InvalidSignatureException::class);
        $handler->assertValidPaymentCallback($forged);
    }
}
