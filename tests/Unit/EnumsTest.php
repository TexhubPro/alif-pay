<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\Enums\CallbackEvent;
use TexHub\AlifPay\Enums\Environment;
use TexHub\AlifPay\Enums\Gate;
use TexHub\AlifPay\Enums\PaymentStatus;
use TexHub\AlifPay\Enums\ResponseCode;
use TexHub\AlifPay\Enums\TokenizationGate;
use TexHub\AlifPay\Enums\TokenizationState;

final class EnumsTest extends TestCase
{
    public function test_environment_base_urls(): void
    {
        $this->assertSame('https://test-web.alif.tj', Environment::Test->baseUrl());
        $this->assertSame('https://web.alif.tj', Environment::Production->baseUrl());
        $this->assertTrue(Environment::Production->isProduction());
    }

    public function test_environment_from_string_defaults_to_test(): void
    {
        $this->assertSame(Environment::Production, Environment::fromString('PRODUCTION'));
        $this->assertSame(Environment::Production, Environment::fromString('live'));
        $this->assertSame(Environment::Test, Environment::fromString('whatever'));
    }

    public function test_settled_is_a_captured_final_state(): void
    {
        $this->assertTrue(PaymentStatus::Settled->isSuccessful());
        $this->assertTrue(PaymentStatus::Settled->isFinal());
        $this->assertSame(PaymentStatus::Settled, PaymentStatus::tryFrom('settled'));
    }

    public function test_tokenization_state_says_which_tokens_can_be_charged(): void
    {
        $this->assertTrue(TokenizationState::Approved->isUsable());
        $this->assertTrue(TokenizationState::Duplicate->isUsable());
        $this->assertFalse(TokenizationState::Failed->isUsable());
        $this->assertFalse(TokenizationState::Removed->isUsable());
        $this->assertFalse(TokenizationState::Accepted->isFinal());
    }

    public function test_payment_status_classification(): void
    {
        $this->assertTrue(PaymentStatus::Ok->isSuccessful());
        $this->assertTrue(PaymentStatus::Ok->isFinal());
        $this->assertTrue(PaymentStatus::Failed->isFinal());
        $this->assertFalse(PaymentStatus::Pending->isFinal());
        $this->assertTrue(PaymentStatus::Canceled->isFailedOrCanceled());
    }

    public function test_response_code_retryability(): void
    {
        $this->assertTrue(ResponseCode::InternalError->isRetryable());
        $this->assertFalse(ResponseCode::NotFound->isRetryable());
        $this->assertFalse(ResponseCode::BadRequest->isRetryable());
        $this->assertFalse(ResponseCode::Declined->isRetryable());
        $this->assertSame(ResponseCode::Accepted, ResponseCode::tryFromInt(202));
        $this->assertSame(ResponseCode::Declined, ResponseCode::tryFromInt(203));
        $this->assertTrue(ResponseCode::Success->isSuccess());
        $this->assertNull(ResponseCode::tryFromInt(null));
    }

    public function test_international_card_gates_use_the_documented_names(): void
    {
        $this->assertSame('visa', Gate::Visa->value);
        $this->assertSame('mastercard', Gate::Mastercard->value);
        $this->assertNull(Gate::tryFrom('vsa'));
        $this->assertTrue(Gate::KortiMilli->acceptsInfoHash());
        $this->assertFalse(Gate::Visa->acceptsInfoHash());
        $this->assertTrue(Gate::Salom->requiresInvoices());
        $this->assertFalse(Gate::Invoice->requiresInvoices());
    }

    public function test_tokenization_binds_visa_and_mastercard_directly(): void
    {
        $this->assertSame('tokenization_visa', TokenizationGate::Visa->value);
        $this->assertSame('tokenization_mastercard', TokenizationGate::Mastercard->value);
        $this->assertTrue(TokenizationGate::Mastercard->isInternationalCard());
        $this->assertFalse(TokenizationGate::Wallet->isInternationalCard());
    }

    public function test_callback_events_split_by_signing_formula(): void
    {
        $this->assertFalse(CallbackEvent::Payment->isTransactionEvent());
        $this->assertTrue(CallbackEvent::Confirm->isTransactionEvent());
        $this->assertTrue(CallbackEvent::Cancel->isTransactionEvent());
        $this->assertSame(CallbackEvent::Tokenize, CallbackEvent::tryFrom('tokenize'));
    }
}
