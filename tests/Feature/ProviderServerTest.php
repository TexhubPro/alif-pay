<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\AlifPay;
use TexHub\AlifPay\Config;
use TexHub\AlifPay\Enums\ProviderAction;
use TexHub\AlifPay\Enums\ProviderCode;
use TexHub\AlifPay\Exceptions\AlifPayException;
use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Exceptions\InvalidSignatureException;
use TexHub\AlifPay\Provider\ProviderResponse;
use TexHub\AlifPay\Provider\ProviderServer;

final class ProviderServerTest extends TestCase
{
    private function server(): ProviderServer
    {
        return new ProviderServer('alif_login', 'alif_secret');
    }

    public function test_it_accepts_the_agreed_basic_credentials(): void
    {
        $server = $this->server();
        $header = 'Basic ' . base64_encode('alif_login:alif_secret');

        $this->assertTrue($server->authorize($header));
        $this->assertTrue($server->authorize(base64_encode('alif_login:alif_secret')));
        $this->assertSame($header, $server->expectedAuthorizationHeader());
    }

    public function test_it_rejects_anything_else(): void
    {
        $server = $this->server();

        $this->assertFalse($server->authorize(null));
        $this->assertFalse($server->authorize(''));
        $this->assertFalse($server->authorize('Basic ' . base64_encode('alif_login:wrong')));
        $this->assertFalse($server->authorize('Bearer ' . base64_encode('alif_login:alif_secret')));

        $this->expectException(InvalidSignatureException::class);
        $server->assertAuthorized('Basic ' . base64_encode('someone:else'));
    }

    public function test_it_parses_a_check_request(): void
    {
        $request = $this->server()->request(json_encode([
            'id' => 12345132564875,
            'action' => 'check',
            'account' => '123000',
            'info' => ['fieldId1' => 'value1'],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(ProviderAction::Check, $request->action);
        $this->assertTrue($request->is(ProviderAction::Check));
        $this->assertSame('12345132564875', $request->id);
        $this->assertSame('123000', $request->account);
        $this->assertNull($request->amount);
        $this->assertSame('value1', $request->info('fieldId1'));
    }

    public function test_it_parses_a_pay_request_and_normalizes_the_amount(): void
    {
        $request = $this->server()->request([
            'id' => 12345132564875,
            'action' => 'pay',
            'account' => '123000',
            'amount' => 100.5,
            'time' => '2006-01-02T15:04:05Z',
            'is_commercial' => true,
        ]);

        $this->assertSame(ProviderAction::Pay, $request->action);
        $this->assertSame('100.50', $request->amount);
        $this->assertSame(100.50, $request->amountAsFloat());
        $this->assertTrue($request->isCommercial);
        $this->assertSame('2006-01-02T15:04:05Z', $request->time);
    }

    public function test_an_unknown_action_is_kept_but_not_typed(): void
    {
        $request = $this->server()->request(['id' => 1, 'action' => 'refund']);

        $this->assertNull($request->action);
        $this->assertSame('refund', $request->rawAction);
    }

    public function test_a_body_that_is_not_json_is_refused(): void
    {
        $this->expectException(AlifPayException::class);

        $this->server()->request('<html>not json</html>');
    }

    public function test_check_answers_with_code_302_and_optional_hints(): void
    {
        $response = ProviderResponse::accountFound('12345132564875', 29.6, 'Баланс: 50.30 смн');

        $this->assertSame([
            'code' => 302,
            'id' => '12345132564875',
            'amount' => '29.60',
            'info_for_client' => 'Баланс: 50.30 смн',
        ], $response->toArray());
        $this->assertSame(200, $response->httpStatus());
    }

    public function test_pay_answers_with_the_providers_own_operation_number(): void
    {
        $paid = ProviderResponse::paid('12345132564875', '4545487');
        $duplicate = ProviderResponse::duplicate('12345132564875', '4545487');

        $this->assertSame(200, $paid->toArray()['code']);
        $this->assertSame('4545487', $paid->toArray()['response_id']);
        $this->assertSame(108, $duplicate->toArray()['code']);
        $this->assertTrue(ProviderCode::DuplicateSuccess->isSuccess());
    }

    public function test_extra_fields_ride_along_for_marketplace_settlements(): void
    {
        $response = ProviderResponse::paid('1', '41279831238')->with([
            'bank_fee' => ['amount' => 0.02, 'acc_num_to' => '17507972000000000000'],
        ]);

        $decoded = json_decode($response->toJson(), true);

        $this->assertSame(0.02, $decoded['bank_fee']['amount']);
        $this->assertSame('41279831238', $decoded['response_id']);
    }

    public function test_a_retryable_code_is_not_reported_as_fatal(): void
    {
        $this->assertFalse(ProviderCode::InProgress->isFatal());
        $this->assertFalse(ProviderCode::InsufficientFunds->isFatal());
        $this->assertFalse(ProviderCode::UnknownError->isFatal());
        $this->assertTrue(ProviderCode::AccountNotFound->isFatal());
        $this->assertTrue(ProviderCode::BadRequest->isFatal());
    }

    public function test_the_entry_point_only_exposes_it_once_configured(): void
    {
        $bare = new AlifPay(new Config('123456', 'super-secret'));

        try {
            $bare->provider();
            $this->fail('Provider credentials should be required.');
        } catch (ConfigurationException) {
            $this->assertTrue(true);
        }

        $configured = new AlifPay(Config::fromArray([
            'terminal_id' => '123456',
            'terminal_password' => 'super-secret',
            'provider_login' => 'alif_login',
            'provider_password' => 'alif_secret',
        ]));

        $this->assertTrue($configured->provider()->authorize($this->server()->expectedAuthorizationHeader()));
    }
}
