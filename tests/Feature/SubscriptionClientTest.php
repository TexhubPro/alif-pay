<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Feature;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\AlifPay;
use TexHub\AlifPay\Config;
use TexHub\AlifPay\Enums\Environment;
use TexHub\AlifPay\Enums\IntervalUnit;
use TexHub\AlifPay\Enums\SubscriptionPeriod;
use TexHub\AlifPay\Enums\SubscriptionStatus;
use TexHub\AlifPay\Exceptions\ApiException;
use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Requests\SubscriptionRequest;
use TexHub\AlifPay\Signature;
use TexHub\AlifPay\Tests\Support\FakeTransport;

final class SubscriptionClientTest extends TestCase
{
    private function alif(FakeTransport $transport): AlifPay
    {
        return new AlifPay(new Config('123456', 'super-secret', Environment::Test), $transport);
    }

    private function signature(): Signature
    {
        return new Signature('123456', 'super-secret');
    }

    public function test_create_signs_into_a_hash_body_field_and_accepts_202(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'code' => 202,
            'message' => 'Запрос в обработке',
            'payload' => ['subscription_id' => 1024, 'subscription_status' => 'accepted'],
        ], 202);

        $created = $this->alif($transport)->subscriptions()->create(
            SubscriptionRequest::make('SUB_ORDER_123', '45781', 'client_12345', '99.9')
                ->period(SubscriptionPeriod::Monthly)
                ->callbackUrl('https://merchant.com/callback')
        );

        $this->assertSame('https://test-web.alif.tj/subscription/create', $transport->lastUrl);
        $body = $transport->lastBody;
        $this->assertSame('123456', $body['terminal_id']);
        $this->assertSame('monthly', $body['period']);
        $this->assertSame(99.9, $body['amount']);
        $this->assertArrayNotHasKey('interval_unit', $body);
        $this->assertArrayNotHasKey('key', $body);
        $this->assertSame($this->signature()->generate('123456SUB_ORDER_12399.9045781'), $body['hash']);

        $this->assertSame(1024, $created->subscriptionId);
        $this->assertSame(SubscriptionStatus::Accepted, $created->status);
        $this->assertFalse($created->status->isActive());
    }

    public function test_a_custom_interval_and_an_end_date_go_out_as_documented(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'code' => 202,
            'message' => 'Запрос в обработке',
            'payload' => ['subscription_id' => 1025, 'subscription_status' => 'accepted'],
        ], 202);

        $this->alif($transport)->subscriptions()->create(
            SubscriptionRequest::make('SUB_ORDER_124', '45782', 'client_12345', 250)
                ->every(3, IntervalUnit::Month)
                ->endDate(new DateTimeImmutable('2027-01-01T00:00:00Z'))
                ->callbackUrl('https://merchant.com/callback')
        );

        $this->assertSame('month', $transport->lastBody['interval_unit']);
        $this->assertSame(3, $transport->lastBody['interval_count']);
        $this->assertSame('2027-01-01T00:00:00+00:00', $transport->lastBody['end_date']);
        $this->assertArrayNotHasKey('period', $transport->lastBody);
    }

    public function test_a_schedule_must_be_given_exactly_once(): void
    {
        $request = SubscriptionRequest::make('SUB_1', '1', 'c', 10)->callbackUrl('https://merchant.com/callback');

        try {
            $request->toArray();
            $this->fail('A request without a schedule was built.');
        } catch (ConfigurationException) {
        }

        $this->expectException(ConfigurationException::class);
        $request->period(SubscriptionPeriod::Weekly)->every(2, IntervalUnit::Week)->toArray();
    }

    public function test_interval_counts_beyond_the_unit_limit_are_refused(): void
    {
        $this->expectException(ConfigurationException::class);

        SubscriptionRequest::make('SUB_1', '1', 'c', 10)->every(4, IntervalUnit::Year);
    }

    public function test_a_non_numeric_token_request_id_is_refused(): void
    {
        $this->expectException(ConfigurationException::class);

        SubscriptionRequest::make('SUB_1', 'abc-uuid', 'c', 10);
    }

    public function test_status_reads_the_schedule_back(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'code' => 200,
            'message' => 'Успешно',
            'payload' => [
                'order_id' => 'SUB_ORDER_123',
                'status' => 'approved',
                'amount' => '99.90',
                'currency' => 'TJS',
                'period' => 'monthly',
                'end_date' => '2027-01-01T00:00:00Z',
            ],
        ]);

        $details = $this->alif($transport)->subscriptions()->status('SUB_ORDER_123');

        $this->assertSame('https://test-web.alif.tj/subscription/status', $transport->lastUrl);
        $this->assertSame($this->signature()->generate('123456SUB_ORDER_123'), $transport->lastBody['hash']);
        $this->assertSame(SubscriptionStatus::Approved, $details->status);
        $this->assertSame(SubscriptionPeriod::Monthly, $details->period);
        $this->assertNull($details->intervalUnit);
        $this->assertSame('99.90', $details->amount);
        $this->assertSame('2027-01-01T00:00:00Z', $details->endDate);
    }

    public function test_cancelling_a_finished_subscription_reports_its_status(): void
    {
        $transport = (new FakeTransport())->willReturnJson([
            'code' => 400,
            'message' => 'Subscription is not cancelable',
            'payload' => ['subscription_status' => 'failed'],
        ], 400);

        try {
            $this->alif($transport)->subscriptions()->cancel('SUB_ORDER_123');
            $this->fail('Expected ApiException');
        } catch (ApiException $exception) {
            $this->assertSame(400, $exception->apiCode);
            $this->assertSame('https://test-web.alif.tj/subscription/cancel', $transport->lastUrl);
            $this->assertSame($this->signature()->generate('123456SUB_ORDER_123'), $transport->lastBody['hash']);
            $this->assertSame('failed', $exception->payload['payload']['subscription_status']);
        }
    }
}
