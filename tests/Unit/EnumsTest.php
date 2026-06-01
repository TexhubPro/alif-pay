<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\Enums\Environment;
use TexHub\AlifPay\Enums\PaymentStatus;
use TexHub\AlifPay\Enums\ResponseCode;

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
        $this->assertTrue(ResponseCode::NotFound->isRetryable());
        $this->assertFalse(ResponseCode::BadRequest->isRetryable());
        $this->assertTrue(ResponseCode::Success->isSuccess());
        $this->assertNull(ResponseCode::tryFromInt(null));
    }
}
