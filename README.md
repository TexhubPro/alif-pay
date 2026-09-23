# TexHub · Alif Pay

**English** · [Русский](README.ru.md)

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012%20%7C%2013-ff2d20.svg)](#laravel)

A clean, framework-agnostic PHP SDK for the **Alif Acquiring (WebCheckout)** payment gateway — payments, tokenization, marketplace split-payments — plus the provider protocol, where Alif calls your server. First-class **Laravel** support.

> Works in plain PHP and any framework. Laravel gets auto-discovery, a config file and a facade for free.

Based on the official documentation: <https://docs.acquiring.alif.tj> and <https://alifcapital.github.io>

---

## Features

- **Standard payments** — Korti Milli, Alif wallet, Salom installments, cash invoices, Visa/Mastercard
- **Tokenization** — bind a card or wallet, then charge it, look it up and unbind it
- **Marketplace** — split a single payment between multiple sellers, hold & confirm delivery
- **Provider endpoint** — the other direction: handle the `check` / `pay` / `status` requests Alif sends you
- **HMAC SHA256 signing** — the exact double-HMAC scheme Alif requires, done for you
- **Webhooks** — typed callback objects for all three shapes + signature verification
- **Pluggable HTTP transport** — cURL by default; inject your own for testing
- **Fully unit-tested**, no network needed
- **Test / Production** environment switch

---

## Installation

```bash
composer require texhub/alif-pay
```

Requirements: **PHP ≥ 8.2** with the `curl`, `json` and `hash` extensions.

---

## Quick start (plain PHP)

```php
use TexHub\AlifPay\AlifPay;
use TexHub\AlifPay\Enums\Environment;
use TexHub\AlifPay\Enums\Gate;
use TexHub\AlifPay\Requests\PaymentRequest;

$alif = AlifPay::make(
    terminalId: 'YOUR_TERMINAL_ID',
    terminalPassword: 'YOUR_TERMINAL_PASSWORD',
    environment: Environment::Test, // Environment::Production when live
);

$response = $alif->payments()->initiate(
    PaymentRequest::make('ORDER_123456', '100.50')
        ->gate(Gate::KortiMilli)
        ->callbackUrl('https://shop.tj/alif/callback')
        ->returnUrl('https://shop.tj/success')
        ->info('Оплата заказа №123456')
        ->phone('992900123456')
);

// Send the customer to the secure payment form:
header('Location: ' . $response->redirectUrl());
```

---

## Environments

| Environment | Base URL |
|---------------------------|----------------------------|
| `Environment::Test` | `https://test-web.alif.tj` |
| `Environment::Production` | `https://web.alif.tj` |

In the test environment you can use Alif's test cards to simulate scenarios (blocked card, insufficient funds, …) without moving real money.

---

## Authorization (how signing works)

Every request is authorized with an HMAC SHA256 token built from the request data:

```
token = HMAC_SHA256( dataToSign, HMAC_SHA256(terminal_password, terminal_id) )
```

The SDK builds the correct `dataToSign` for each operation automatically:

| Operation | `dataToSign` | Sent in |
|--------------------------|--------------------------------------------------|---------------|
| Payment / Marketplace | `terminal_id + order_id + amount + callback_url` | body `token` |
| Tokenization binding | `terminal_id + phone + gate` | body `token` |
| Status check | `terminal_id + orderId` | body `token` |
| Cancel | `terminal_id + transaction_id + amount` | body `token` |
| Confirm delivery | `terminal_id + transaction_id + amount` | body `token` |
| Confirm VSA/MCR delivery | `terminal_id + parent_transaction_id` | body `token` |
| Charge a bound token | `terminal_id + order_id + amount + token` | `hash` header |
| Tokenization status | `terminal_id + request_id` | `hash` header |
| Remove a token | `terminal_id + token` | `hash` header |

The three newest endpoints carry the signature in a `hash` header, and their body `token` is the UUID of a bound payment method instead.

You never call the signer manually — but it's available via `$alif->signature()` if needed.

---

## Payments

### Gateways (`Gate`)

| Enum | `gate` value | Method |
|-----------------------------|------------------------|-----------------------------|
| `Gate::KortiMilli` | `korti_milli` | National card *(default)* |
| `Gate::Wallet` | `wallet` | Alif mobi wallet |
| `Gate::Salom` | `salom` | Salom installment |
| `Gate::Invoice` | `invoice` | Cash invoice |
| `Gate::Visa` | `vsa` | Visa |
| `Gate::Mastercard` | `mcr` | Mastercard |
| `Gate::CybersourceCheckout` | `cybersource_checkout` | Cybersource hosted checkout |

Alif supports more methods than it publishes gate values for. For one the table does not name, pass it raw:

```php
PaymentRequest::make('ORDER_1', '100.00')->gateValue('google_pay');
```

### Salom installment (with invoice items)

```php
use TexHub\AlifPay\Requests\InvoiceItem;

$response = $alif->payments()->initiate(
    PaymentRequest::make('ORDER_345678', '1500.00')
        ->gate(Gate::Salom)
        ->callbackUrl('https://shop.tj/alif/callback')
        ->returnUrl('https://shop.tj/success')
        ->phone('992900111222')
        ->addInvoiceItem(new InvoiceItem(
            name: 'Смартфон Samsung Galaxy A54',
            category: 'Электроника',
            quantity: 1,
            price: '1500.00',
            vatPercent: '0',
        ))
);
```

### Cash invoice (with deadline)

```php
$alif->payments()->initiate(
    PaymentRequest::make('ORDER_678900', '1200.00')
        ->gate(Gate::Invoice)
        ->callbackUrl('https://shop.tj/alif/callback')
        ->returnUrl('https://shop.tj/success')
        ->deadline('2025-11-29T07:59:59Z')
);
```

### Check status / cancel

```php
$status = $alif->payments()->checkStatus('ORDER_123456');

$status->get('status');         // "ok"
$status->get('transactionId');  // "789012"

$alif->payments()->cancel(transactionId: '789012', amount: '100.50', reason: 'Возврат по заявке клиента');
```

A found transaction comes back as itself — `orderId`, `transactionId`, `status`, `token`, `amount` — with no `code` field; only failures carry one. Verify its `token` before acting on the status (see [Verifying what Alif sends back](#verifying-what-alif-sends-back)).

Cancellation is full-only for standard payments, and Salom can only be cancelled within 14 days.

---

## Tokenization

```php
use TexHub\AlifPay\Enums\TokenizationGate;
use TexHub\AlifPay\Requests\TokenizationRequest;

$response = $alif->tokenization()->initiate(
    TokenizationRequest::make('ORDER_123456', '+992900123456', TokenizationGate::Wallet)
        ->callbackUrl('https://shop.tj/alif/tokenize-callback')
        ->returnUrl('https://shop.tj/success')
        ->clientId('client_12345')
);

header('Location: ' . $response->redirectUrl());
```

Available gates: `KortiMilli`, `Wallet`, `Salom`, `Tcell`, `Megafon`, `Babilon`, `ZetMobile`, `Procard` (Visa/Mastercard).

The phone must be a Tajik number. Write it however you like — `+992900123456`, `992900123456`, `900 123-456` — the SDK normalizes it to the documented form before signing it.

### Charging a bound token

Binding is only half of it. Once the tokenization callback hands you a token, charge it:

```php
use TexHub\AlifPay\Requests\TokenChargeRequest;

$alif->tokenization()->charge(
    TokenChargeRequest::make('ORDER_123456', '100.00', $savedToken)
        ->callbackUrl('https://shop.tj/alif/callback')
        ->email('customer@example.com')
        ->info('Подписка на месяц')
);
```

Add splits to make it a marketplace charge:

```php
TokenChargeRequest::make('ORDER_1', '100.00', $savedToken)
    ->callbackUrl('https://shop.tj/alif/callback')
    ->splitTo('TERM_001', '70.00')
    ->splitTo('TERM_002', '30.00');
```

### Status and removal

```php
$state = $alif->tokenization()->status('ORDER_123456');
$state->get('payload')['status'];   // accepted | approved | duplicate | failed | removed

$alif->tokenization()->remove('ORDER_123456', $savedToken);
```

`TokenizationState` types those statuses, and `$callback->state()?->isUsable()` answers the only question that usually matters: can this token be charged?

---

## Marketplace (split-payment)

```php
use TexHub\AlifPay\Requests\MarketplaceRequest;

$response = $alif->marketplace()->initiate(
    MarketplaceRequest::make('MP_ORDER_123456', '500.00')
        ->gate(Gate::KortiMilli)
        ->callbackUrl('https://shop.tj/alif/mp-callback')
        ->returnUrl('https://shop.tj/success')
        ->splitTo('partner_terminal_1', '300.00')
        ->splitTo('partner_terminal_2', '200.00')
);
```

> The split total **must** equal the order amount — the SDK validates this before sending.

A seller's share can carry its own invoice lines, and a Salom installment condition:

```php
use TexHub\AlifPay\Requests\InvoiceItem;
use TexHub\AlifPay\Requests\TerminalSplit;

$alif->marketplace()->initiate(
    MarketplaceRequest::make('MP_SALOM_1', '1200.00')
        ->gate(Gate::Salom)
        ->callbackUrl('https://shop.tj/alif/mp-callback')
        ->returnUrl('https://shop.tj/success')
        ->addSplit(
            TerminalSplit::make('SELLER_005', '1200.00', conditionId: 12)
                ->addInvoiceItem(new InvoiceItem(
                    name: 'Смартфон Samsung Galaxy A54',
                    category: 'smartphones',
                    quantity: 1,
                    price: '1200.00',
                ))
        )
);
```

Funds are held until delivery is confirmed:

```php
// All methods except Visa/Mastercard — a smaller amount confirms partially:
$alif->marketplace()->confirmDelivery(transactionId: '789013', amount: '300.00');

// Visa / Mastercard — every child transaction in one call:
use TexHub\AlifPay\Requests\DeliveryConfirmation;

$alif->marketplace()->confirmVsaMcrDelivery(
    '789012',
    new DeliveryConfirmation('789013', '150.00'),
    new DeliveryConfirmation('789014', '350.00'),
);

// Status & cancellation (a child transaction, never the parent):
$alif->marketplace()->checkStatus('MP_ORDER_123456');
$alif->marketplace()->cancel(transactionId: '789013', amount: '300.00', reason: 'Отмена заказа покупателем');
```

---

## Webhooks (callbacks)

Alif sends a `POST` to your `callback_url` on every status change. Respond with **HTTP 200** or it will retry.

### Payment / marketplace callback

```php
use TexHub\AlifPay\Enums\PaymentStatus;

$callback = $alif->webhooks()->paymentCallback(file_get_contents('php://input'));

// Verify authenticity before trusting it (see note below):
if (! $alif->webhooks()->verifyPaymentCallback($callback)) {
    http_response_code(400);
    exit;
}

match ($callback->status) {
    PaymentStatus::Ok       => markOrderPaid($callback->orderId, $callback->amount),
    PaymentStatus::Failed,
    PaymentStatus::Canceled => markOrderFailed($callback->orderId),
    default                 => null, // pending / to_approve
};

http_response_code(200);
echo 'OK';
```

For marketplace, `$callback->subTransactions` holds the per-partner breakdown and `$callback->isMarketplace()` is `true`.

### Delivery and cancellation events

Marketplace posts two more shapes to the same URL — one per sub-transaction, after a delivery confirmation or a cancellation. Let the handler pick:

```php
use TexHub\AlifPay\Webhook\DeliveryCallback;
use TexHub\AlifPay\Webhook\PaymentCallback;

$callback = $alif->webhooks()->callback(file_get_contents('php://input'));

match (true) {
    $callback instanceof PaymentCallback  => handlePayment($callback),
    $callback instanceof DeliveryCallback => handleDelivery($callback),
};
```

A `DeliveryCallback` carries `terminalId`, `transactionId`, `status`, `event`, `parentTransactionId` and `parentStatus` — but **no token**, so it cannot be verified. Treat it as a hint that something moved and confirm with `checkStatus()`.

### Tokenization callback

> The tokenization callback has a **different** structure (result code at the root, data under `payload`).

```php
$callback = $alif->webhooks()->tokenizationCallback(file_get_contents('php://input'));

if ($callback->isSuccessful()) {
    saveToken($callback->orderId, $callback->token); // store for repeat charges
}

http_response_code(200);
```

### Verifying what Alif sends back

Alif signs its own answers with the same HMAC scheme, over `orderId . status . transactionId` — the string the documentation gives for the status response, and the fields a callback carries. That is what the SDK checks by default:

```php
$alif->webhooks()->verifyPaymentCallback($callback);            // callbacks
$alif->webhooks()->verifyStatusResponse($alif->payments()->checkStatus($orderId)); // status lookups
```

Because the status is inside the signed string, a failed payment replayed as a successful one no longer verifies.

If your terminal is set up differently, pass your own signing string:

```php
$alif->webhooks()->verifyPaymentCallback($callback, dataToSign: $yourString);
// or the low-level check:
$alif->webhooks()->verifyToken($yourString, $callback->token);
```

**A verified callback is still not a settled payment.** The callback URL is public, so before handing anything over, confirm the outcome yourself:

```php
$status = $alif->payments()->checkStatus($callback->orderId);

if ($alif->webhooks()->verifyStatusResponse($status) && $status->get('status') === 'ok') {
    // now it is paid
}
```

A found transaction comes back as itself — `orderId`, `transactionId`, `status`, `token`, `amount` — with no `code` field; only failures carry one.

---

## Error handling

The gateway replies with an HTTP status that mirrors the business `code` — a duplicate order is HTTP 208 with `code: 208`. The SDK turns any failure into an `ApiException`:

```php
use TexHub\AlifPay\Exceptions\ApiException;
use TexHub\AlifPay\Exceptions\TransportException;

try {
    $response = $alif->payments()->initiate($request);
} catch (ApiException $e) {
    $e->apiCode;        // 208, 400, 401, 403, 404, 500
    $e->apiMessage;     // human-readable message (RU)
    $e->isDuplicate();  // true for code 208
    $e->isRetryable();  // true for 404 / 500
} catch (TransportException $e) {
    // network/connection failure
}
```

| Code | Meaning | Retry |
|------|--------------------|-------|
| 200 | Success | — |
| 208 | Duplicate order_id | No |
| 400 | Validation error | No |
| 401 | Auth error (token) | No |
| 403 | Invalid key | No |
| 404 | Not found | Yes |
| 500 | Internal error | Yes |

Status lookups are the exception: a transaction that was found carries no `code` at all.

---

## Provider protocol

The other direction, specified at <https://alifcapital.github.io/providers>: Alif calls **your** endpoint so its customers can pay for your service at its tills and in its app. It shares nothing with acquiring — no HMAC, a separate login and password, its own result codes.

```dotenv
ALIF_PAY_PROVIDER_LOGIN=your_login
ALIF_PAY_PROVIDER_PASSWORD=your_password
```

```php
use TexHub\AlifPay\Enums\ProviderAction;
use TexHub\AlifPay\Enums\ProviderCode;
use TexHub\AlifPay\Provider\ProviderResponse;

$provider = $alif->provider();

if (! $provider->authorize($_SERVER['HTTP_AUTHORIZATION'] ?? null)) {
    $provider->send(ProviderResponse::code(ProviderCode::Unauthorized));
    exit;
}

$request = $provider->request(file_get_contents('php://input'));

$response = match ($request->action) {
    ProviderAction::Check  => findSubscriber($request->account)
        ? ProviderResponse::accountFound($request->id, infoForClient: 'Баланс: 50.30 смн')
        : ProviderResponse::code(ProviderCode::AccountNotFound, $request->id),

    ProviderAction::Pay    => ProviderResponse::paid($request->id, credit($request->account, $request->amount)),

    ProviderAction::Status => ProviderResponse::status($request->id, ProviderCode::Success, $storedOperationId),

    default                => ProviderResponse::code(ProviderCode::BadRequest, $request->id),
};

$provider->send($response);
```

`$request->id` is the payment's identifier in Alif's system and is your idempotency key: a repeated `pay` for an id you already credited must answer `ProviderResponse::duplicate()`, never credit twice.

Mind the retry semantics — `ProviderCode::isFatal()` tells you which codes end the payment and which ones Alif will retry for up to 24 hours:

```php
ProviderCode::InsufficientFunds->isFatal();  // false — Alif retries
ProviderCode::AccountNotFound->isFatal();    // true  — Alif gives up
```

---

## <a name="laravel"></a> Laravel

The service provider and `AlifPay` facade are **auto-discovered**. Publish the config:

```bash
php artisan vendor:publish --tag=alif-pay-config
```

Add credentials to `.env`:

```dotenv
ALIF_PAY_ENVIRONMENT=test
ALIF_PAY_TERMINAL_ID=your_terminal_id
ALIF_PAY_TERMINAL_PASSWORD=your_terminal_password
ALIF_PAY_CALLBACK_URL=https://shop.tj/alif/callback
ALIF_PAY_RETURN_URL=https://shop.tj/success
ALIF_PAY_TIMEOUT=30

# Only for the provider protocol:
ALIF_PAY_PROVIDER_LOGIN=
ALIF_PAY_PROVIDER_PASSWORD=
```

Use the facade (callback/return URL fall back to config):

```php
use TexHub\AlifPay\Laravel\AlifPay;
use TexHub\AlifPay\Enums\Gate;
use TexHub\AlifPay\Requests\PaymentRequest;

$response = AlifPay::payments()->initiate(
    PaymentRequest::make('ORDER_'.$order->id, $order->total)->gate(Gate::KortiMilli)
);

return redirect()->away($response->redirectUrl());
```

…or resolve from the container / inject it:

```php
public function pay(\TexHub\AlifPay\AlifPay $alif) { /* ... */ }
```

### Example callback controller

```php
use Illuminate\Http\Request;
use TexHub\AlifPay\Laravel\AlifPay;
use TexHub\AlifPay\Enums\PaymentStatus;

public function callback(Request $request)
{
    $callback = AlifPay::webhooks()->paymentCallback($request->getContent());

    if ($callback->status === PaymentStatus::Ok) {
        Order::where('reference', $callback->orderId)->update(['status' => 'paid']);
    }

    return response('OK', 200);
}
```

> Exclude the callback route from CSRF protection (`VerifyCsrfToken::$except`) since it's a server-to-server `POST`.

---

## Testing

The SDK ships with a fake transport so you can test without hitting the network:

```php
use TexHub\AlifPay\AlifPay;
use TexHub\AlifPay\Config;
use TexHub\AlifPay\Tests\Support\FakeTransport;

$transport = (new FakeTransport())->willReturnJson([
    'code' => 200, 'message' => 'Успешно', 'url' => 'https://web.alif.tj/abc',
]);

$alif = new AlifPay(new Config('id', 'secret'), $transport);
// ... assert on $transport->lastBody / lastHeaders / lastUrl
```

Run the package test suite:

```bash
composer install
composer test          # or: vendor/bin/phpunit
```

---

## Architecture

```
src/
├── AlifPay.php              # entry point — payments()/tokenization()/marketplace()/webhooks()/provider()
├── Config.php               # immutable configuration
├── Signature.php            # HMAC SHA256 double-hash signer
├── Enums/                   # Environment, Gate, PaymentStatus, TokenizationState, ProviderCode, …
├── Http/                    # Transport interface, CurlTransport, Response
├── Requests/                # PaymentRequest, TokenChargeRequest, MarketplaceRequest, TerminalSplit, …
├── Clients/                 # PaymentClient, TokenizationClient, MarketplaceClient
├── Webhook/                 # callback DTOs + WebhookHandler
├── Provider/                # the endpoint Alif calls: check / pay / status
├── Exceptions/              # ApiException, TransportException, …
└── Laravel/                 # ServiceProvider + Facade
```

Developer documentation — architecture, every signing string, per-area notes and the open questions in Alif's own docs — lives in [`docs/dev/`](docs/dev/README.md).

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md). Version 2.0.0 changes the marketplace split field and removes `TerminalAmount` — read it before upgrading.

---

## License

MIT © TexHub Pro — built by Mahmudi Shodmehr.
