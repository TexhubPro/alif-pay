<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\Enums\PaymentStatus;
use TexHub\AlifPay\Enums\TokenizationStatus;
use TexHub\AlifPay\Exceptions\InvalidSignatureException;
use TexHub\AlifPay\Enums\CallbackEvent;
use TexHub\AlifPay\Signature;
use TexHub\AlifPay\Webhook\DeliveryCallback;
use TexHub\AlifPay\Webhook\PaymentCallback;
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
            'event' => 'payment',
            'info' => 'Оплата заказа №123456',
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
        $this->assertSame(CallbackEvent::Payment, $callback->event);
        $this->assertSame('Оплата заказа №123456', $callback->info);
    }

    public function test_it_parses_marketplace_sub_transactions(): void
    {
        $callback = $this->handler()->paymentCallback([
            'orderId' => 'MP_ORDER_1',
            'transactionId' => '789012',
            'status' => 'ok',
            'event' => 'payment',
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
            'Code' => 1,
            'Message' => 'Успешно',
            'Messages' => null,
            'ReasonCode' => '',
            'Payload' => [
                'transactionId' => 12345,
                'orderId' => 'ORDER_123456',
                'token' => str_repeat('a', 64),
                'account' => '9929****506',
                'status' => 'approved',
                'event' => 'tokenize',
                'transaction_type' => 'wallet',
            ],
        ]);

        $this->assertSame(TokenizationStatus::Success, $callback->statusCode);
        $this->assertSame('Успешно', $callback->message);
        $this->assertSame('wallet', $callback->method());
        $this->assertSame('approved', $callback->status);
        $this->assertTrue($callback->isSuccessful());
        $this->assertSame('ORDER_123456', $callback->orderId);
        $this->assertSame(12345, $callback->transactionId);
    }

    public function test_it_verifies_a_payment_callback_token(): void
    {
        $handler = $this->handler();
        $signature = new Signature('123456', 'super-secret');

        $token = $signature->generate('ORDER_1ok789012');

        $callback = $handler->paymentCallback([
            'orderId' => 'ORDER_1',
            'transactionId' => '789012',
            'amount' => 100.0,
            'status' => 'ok',
            'token' => $token,
        ]);

        $this->assertTrue($handler->verifyPaymentCallback($callback));

        $forged = $handler->paymentCallback([
            'orderId' => 'ORDER_1',
            'transactionId' => '789012',
            'amount' => 100.0,
            'status' => 'ok',
            'token' => 'forged',
        ]);
        $this->assertFalse($handler->verifyPaymentCallback($forged));

        $this->expectException(InvalidSignatureException::class);
        $handler->assertValidPaymentCallback($forged);
    }

    public function test_a_callback_claiming_a_different_outcome_does_not_verify(): void
    {
        $handler = $this->handler();
        $signature = new Signature('123456', 'super-secret');

        $callback = $handler->paymentCallback([
            'orderId' => 'ORDER_1',
            'transactionId' => '789012',
            'amount' => 100.0,
            'status' => 'ok',
            'token' => $signature->generate('ORDER_1failed789012'),
        ]);

        $this->assertFalse($handler->verifyPaymentCallback($callback));
    }

    public function test_it_verifies_a_status_lookup_response(): void
    {
        $handler = $this->handler();
        $signature = new Signature('123456', 'super-secret');

        $response = [
            'orderId' => 'ORDER_1',
            'transactionId' => '789012',
            'status' => 'ok',
            'amount' => 100.0,
            'token' => $signature->generate('ORDER_1ok789012'),
        ];

        $this->assertTrue($handler->verifyStatusResponse($response));

        $response['token'] = $signature->generate('ORDER_1ok789013');
        $this->assertFalse($handler->verifyStatusResponse($response));

        $this->assertFalse($handler->verifyStatusResponse(['orderId' => 'ORDER_1', 'status' => 'ok']));
    }

    public function test_it_parses_a_delivery_confirmation_event(): void
    {
        $callback = $this->handler()->deliveryCallback(json_encode([
            'terminal_id' => 'partner_terminal_1',
            'transaction_id' => '789013',
            'status' => 'ok',
            'event' => 'confirm',
            'parent_transaction_id' => '789012',
            'parent_status' => 'ok',
        ], JSON_THROW_ON_ERROR));

        $this->assertSame('partner_terminal_1', $callback->terminalId);
        $this->assertSame('789013', $callback->transactionId);
        $this->assertSame(CallbackEvent::Confirm, $callback->event);
        $this->assertTrue($callback->isConfirmation());
        $this->assertFalse($callback->isCancellation());
        $this->assertSame('789012', $callback->parentTransactionId);
        $this->assertTrue($callback->isParentFinal());
    }

    public function test_a_partial_cancellation_leaves_the_parent_open(): void
    {
        $callback = $this->handler()->deliveryCallback([
            'terminal_id' => 'partner_terminal_1',
            'transaction_id' => '789013',
            'status' => 'partially_canceled',
            'event' => 'cancel',
            'parent_transaction_id' => '789012',
            'parent_status' => 'partially_canceled',
        ]);

        $this->assertTrue($callback->isCancellation());
        $this->assertSame(PaymentStatus::PartiallyCanceled, $callback->status);
        $this->assertFalse($callback->isParentFinal());
    }

    public function test_the_dispatcher_tells_payment_and_transaction_events_apart(): void
    {
        $handler = $this->handler();

        $payment = $handler->callback([
            'orderId' => 'ORDER_1',
            'transactionId' => '789012',
            'status' => 'ok',
            'event' => 'payment',
            'token' => str_repeat('a', 64),
            'amount' => 100.0,
        ]);

        $legacy = $handler->callback([
            'orderId' => 'ORDER_1',
            'transactionId' => '789012',
            'status' => 'ok',
            'amount' => 100.0,
        ]);

        $standardCancel = $handler->callback([
            'terminal_id' => '123456',
            'transaction_id' => '789012',
            'status' => 'canceled',
            'event' => 'cancel',
            'token' => str_repeat('b', 64),
        ]);

        $event = $handler->callback([
            'terminal_id' => 'partner_terminal_1',
            'transaction_id' => '789013',
            'status' => 'ok',
            'event' => 'confirm',
            'parent_transaction_id' => '789012',
            'parent_status' => 'ok',
        ]);

        $this->assertInstanceOf(PaymentCallback::class, $payment);
        $this->assertInstanceOf(PaymentCallback::class, $legacy);
        $this->assertInstanceOf(DeliveryCallback::class, $event);
        $this->assertInstanceOf(DeliveryCallback::class, $standardCancel);
        $this->assertFalse($standardCancel->isSubTransaction());
    }

    public function test_a_standard_cancellation_callback_is_verified_with_its_own_formula(): void
    {
        $handler = $this->handler();
        $signature = new Signature('123456', 'super-secret');

        $callback = $handler->callback(json_encode([
            'terminal_id' => '123456',
            'transaction_id' => '789012',
            'status' => 'canceled',
            'event' => 'cancel',
            'token' => $signature->generate('123456789012canceled'),
        ], JSON_THROW_ON_ERROR));

        $this->assertInstanceOf(DeliveryCallback::class, $callback);
        $this->assertTrue($callback->isCancellation());
        $this->assertSame(PaymentStatus::Canceled, $callback->status);
        $this->assertTrue($handler->verifyDeliveryCallback($callback));
        $this->assertTrue($handler->verifyCallback($callback));

        $forged = $handler->deliveryCallback([
            'terminal_id' => '123456',
            'transaction_id' => '789012',
            'status' => 'canceled',
            'event' => 'cancel',
            'token' => $signature->generate('ORDER_1canceled789012'),
        ]);
        $this->assertFalse($handler->verifyDeliveryCallback($forged));

        $this->expectException(InvalidSignatureException::class);
        $handler->assertValidDeliveryCallback($forged);
    }

    public function test_a_marketplace_confirmation_is_verified_with_the_transaction_formula(): void
    {
        $handler = $this->handler();
        $signature = new Signature('123456', 'super-secret');

        $callback = $handler->deliveryCallback([
            'terminal_id' => '200001',
            'transaction_id' => '789013',
            'status' => 'ok',
            'event' => 'confirm',
            'token' => $signature->generate('200001789013ok'),
            'parent_transaction_id' => '789012',
            'parent_status' => 'ok',
        ]);

        $this->assertTrue($callback->isSubTransaction());
        $this->assertTrue($handler->verifyDeliveryCallback($callback));
        $this->assertFalse($handler->verifyDeliveryCallback($handler->deliveryCallback([
            'terminal_id' => '200001',
            'transaction_id' => '789013',
            'status' => 'ok',
            'event' => 'confirm',
        ])));
    }

    public function test_a_payment_callback_is_verified_with_the_order_formula_by_the_dispatcher(): void
    {
        $handler = $this->handler();
        $signature = new Signature('123456', 'super-secret');

        $callback = $handler->callback([
            'orderId' => 'ORDER_1',
            'transactionId' => '789012',
            'status' => 'ok',
            'event' => 'payment',
            'token' => $signature->generate('ORDER_1ok789012'),
            'amount' => 100.0,
        ]);

        $this->assertInstanceOf(PaymentCallback::class, $callback);
        $this->assertTrue($handler->verifyCallback($callback));
    }

    public function test_a_lowercase_tokenization_callback_still_parses(): void
    {
        $callback = $this->handler()->tokenizationCallback([
            'code' => 9,
            'message' => 'Привязка завершилась с ошибкой',
            'payload' => [
                'transactionId' => 12346,
                'orderId' => 'ORDER_2',
                'token' => '',
                'status' => 'failed',
                'transaction_type' => 'tokenization_wallet',
            ],
        ]);

        $this->assertSame(TokenizationStatus::Rejected, $callback->statusCode);
        $this->assertSame('wallet', $callback->method());
        $this->assertFalse($callback->isSuccessful());
    }

    public function test_a_status_response_can_be_asserted_instead_of_checked(): void
    {
        $handler = $this->handler();
        $signature = new Signature('123456', 'super-secret');

        $handler->assertValidStatusResponse([
            'orderId' => 'ORDER_1',
            'transactionId' => '789012',
            'status' => 'ok',
            'token' => $signature->generate('ORDER_1ok789012'),
        ]);

        $this->expectException(InvalidSignatureException::class);
        $handler->assertValidStatusResponse([
            'orderId' => 'ORDER_1',
            'transactionId' => '789012',
            'status' => 'ok',
            'token' => 'forged',
        ]);
    }
}
