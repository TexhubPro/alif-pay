# TexHub · Alif Pay

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012%20%7C%2013-ff2d20.svg)](#laravel)

A clean, framework-agnostic PHP SDK for the **Alif Acquiring (WebCheckout)** payment gateway — payments, tokenization and marketplace split-payments — with first-class **Laravel** support.

> Works in plain PHP and any framework. Laravel gets auto-discovery, a config file and a facade for free.

Based on the official documentation: <https://docs.acquiring.alif.tj/intro>

---

## ✨ Features

- 💳 **Standard payments** — Korti Milli, Alif wallet, Salom installments, cash invoices, Visa/Mastercard
- 🔁 **Tokenization** — bind cards/wallets for repeat charges
- 🛒 **Marketplace** — split a single payment between multiple sellers, hold & confirm delivery
- 🔐 **HMAC SHA256 signing** — the exact double-HMAC scheme Alif requires, done for you
- 📩 **Webhooks** — typed callback objects + signature verification
- 🧩 **Pluggable HTTP transport** — cURL by default; inject your own for testing
- ✅ **Fully unit-tested**, no network needed
- 🟢 **Test / Production** environment switch

---

## 📦 Installation

```bash
composer require texhub/alif-pay
```

Requirements: **PHP ≥ 8.2** with the `curl`, `json` and `hash` extensions.

---

## 🚀 Quick start (plain PHP)

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

## 🌍 Environments

| Environment               | Base URL                   |
|---------------------------|----------------------------|
| `Environment::Test`       | `https://test-web.alif.tj` |
| `Environment::Production` | `https://web.alif.tj`      |

In the test environment you can use Alif's test cards to simulate scenarios (blocked card, insufficient funds, …) without moving real money.

---

## 🔐 Authorization (how signing works)

Every request is authorized with an HMAC SHA256 token built from the request data:

```
token = HMAC_SHA256( dataToSign, HMAC_SHA256(terminal_password, terminal_id) )
```

The SDK builds the correct `dataToSign` for each operation automatically:

| Operation                | `dataToSign`                                     |
|--------------------------|--------------------------------------------------|
| Payment / Marketplace    | `terminal_id + order_id + amount + callback_url` |
| Tokenization             | `terminal_id + phone + gate`                     |
| Confirm delivery         | `terminal_id + transaction_id + amount`          |
| Confirm VSA/MCR delivery | `terminal_id + parent_transaction_id`            |

You never call the signer manually — but it's available via `$alif->signature()` if needed.

---

## 💳 Payments

### Gateways (`Gate`)

| Enum                        | `gate` value           | Method                      |
|-----------------------------|------------------------|-----------------------------|
| `Gate::KortiMilli`          | `korti_milli`          | National card *(default)*   |
| `Gate::Wallet`              | `wallet`               | Alif mobi wallet            |
| `Gate::Salom`               | `salom`                | Salom installment           |
| `Gate::Invoice`             | `invoice`              | Cash invoice                |
| `Gate::Visa`                | `vsa`                  | Visa                        |
| `Gate::Mastercard`          | `mcr`                  | Mastercard                  |
| `Gate::CybersourceCheckout` | `cybersource_checkout` | Cybersource hosted checkout |

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
$alif->payments()->cancel(transactionId: '789012', amount: '100.50');
```

---

## 🔁 Tokenization

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

---

## 🛒 Marketplace (split-payment)

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

Funds are held until delivery is confirmed:

```php
// All methods except Visa/Mastercard:
$alif->marketplace()->confirmDelivery(transactionId: '789013', amount: '300.00');

// Visa / Mastercard:
$alif->marketplace()->confirmVsaMcrDelivery(parentTransactionId: '789012');

// Status & cancellation:
$alif->marketplace()->checkStatus('MP_ORDER_123456');
$alif->marketplace()->cancel(transactionId: '789013', amount: '300.00');
```

---

## 📩 Webhooks (callbacks)

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

### Tokenization callback

> ⚠️ The tokenization callback has a **different** structure (result code at the root, data under `payload`).

```php
$callback = $alif->webhooks()->tokenizationCallback(file_get_contents('php://input'));

if ($callback->isSuccessful()) {
    saveToken($callback->orderId, $callback->token); // store for repeat charges
}

http_response_code(200);
```

### A note on callback signature verification

Alif signs callbacks with the same HMAC scheme, but the exact concatenation order for the **callback** token is not published. `verifyPaymentCallback()` defaults to `orderId . amount`; if your terminal differs, pass your own signing string:

```php
$alif->webhooks()->verifyPaymentCallback($callback, dataToSign: $yourString);
// or the low-level check:
$alif->webhooks()->verifyToken($yourString, $callback->token);
```

---

## ⚙️ Error handling

The gateway always replies with HTTP 200 and a business `code`. The SDK turns any non-`200` code into an `ApiException`:

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

| Code | Meaning            | Retry |
|------|--------------------|-------|
| 200  | Success            | —     |
| 208  | Duplicate order_id | No    |
| 400  | Validation error   | No    |
| 401  | Auth error (token) | No    |
| 403  | Invalid key        | No    |
| 404  | Not found          | Yes   |
| 500  | Internal error     | Yes   |

---

## <a name="laravel"></a>🧩 Laravel

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

## 🧪 Testing

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

## 📚 Architecture

```
src/
├── AlifPay.php              # entry point — payments()/tokenization()/marketplace()/webhooks()
├── Config.php               # immutable configuration
├── Signature.php            # HMAC SHA256 double-hash signer
├── Enums/                   # Environment, Gate, TokenizationGate, PaymentStatus, …
├── Http/                    # Transport interface, CurlTransport, Response
├── Requests/                # PaymentRequest, TokenizationRequest, MarketplaceRequest, …
├── Clients/                 # PaymentClient, TokenizationClient, MarketplaceClient
├── Webhook/                 # callback DTOs + WebhookHandler
├── Exceptions/              # ApiException, TransportException, …
└── Laravel/                 # ServiceProvider + Facade
```

---

## License

MIT © TexHub Pro — built by Mahmudi Shodmehr.
