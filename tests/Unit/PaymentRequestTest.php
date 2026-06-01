<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\Enums\Gate;
use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Requests\InvoiceItem;
use TexHub\AlifPay\Requests\PaymentRequest;

final class PaymentRequestTest extends TestCase
{
    public function test_it_builds_a_minimal_body(): void
    {
        $body = PaymentRequest::make('ORDER_1', 100.5)
            ->gate(Gate::KortiMilli)
            ->callbackUrl('https://shop.tj/cb')
            ->returnUrl('https://shop.tj/ok')
            ->info('Order #1')
            ->phone('992900123456')
            ->toArray();

        $this->assertSame('ORDER_1', $body['order_id']);
        $this->assertSame('100.50', $body['amount']);
        $this->assertSame('korti_milli', $body['gate']);
        $this->assertSame('https://shop.tj/cb', $body['callback_url']);
        $this->assertSame('https://shop.tj/ok', $body['return_url']);
        $this->assertSame('Order #1', $body['info']);
        $this->assertArrayNotHasKey('token', $body, 'token is injected by the client, not the request');
    }

    public function test_it_uses_config_default_urls_when_missing(): void
    {
        $body = PaymentRequest::make('ORDER_2', 10)
            ->toArray('https://default/cb', 'https://default/ok');

        $this->assertSame('https://default/cb', $body['callback_url']);
        $this->assertSame('https://default/ok', $body['return_url']);
    }

    public function test_it_requires_a_callback_url(): void
    {
        $this->expectException(ConfigurationException::class);
        PaymentRequest::make('ORDER_3', 10)->returnUrl('https://x/ok')->toArray();
    }

    public function test_it_nests_invoice_items_for_salom(): void
    {
        $body = PaymentRequest::make('ORDER_4', 1500)
            ->gate(Gate::Salom)
            ->callbackUrl('https://shop.tj/cb')
            ->returnUrl('https://shop.tj/ok')
            ->addInvoiceItem(new InvoiceItem('Phone', 'Electronics', 1, '1500.00'))
            ->toArray();

        $this->assertArrayHasKey('invoices', $body);
        $this->assertSame('Phone', $body['invoices']['invoices'][0]['name']);
        $this->assertSame('0', $body['invoices']['invoices'][0]['vat_percent']);
    }

    public function test_empty_order_id_is_rejected(): void
    {
        $this->expectException(ConfigurationException::class);
        PaymentRequest::make('  ', 10);
    }
}
