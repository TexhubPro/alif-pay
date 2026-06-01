<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * Laravel facade for the Alif Pay client.
 *
 * @method static \TexHub\AlifPay\Clients\PaymentClient      payments()
 * @method static \TexHub\AlifPay\Clients\TokenizationClient tokenization()
 * @method static \TexHub\AlifPay\Clients\MarketplaceClient  marketplace()
 * @method static \TexHub\AlifPay\Webhook\WebhookHandler     webhooks()
 * @method static \TexHub\AlifPay\Config                     config()
 * @method static \TexHub\AlifPay\Signature                  signature()
 *
 * @see \TexHub\AlifPay\AlifPay
 */
class AlifPay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'alif-pay';
    }
}
