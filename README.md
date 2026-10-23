# TexHub · Alif Pay

**English** · [Русский](README.ru.md)

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012%20%7C%2013-ff2d20.svg)](#laravel)

A clean, framework-agnostic PHP SDK for the **Alif Acquiring (WebCheckout)** payment gateway — payments, tokenization, subscriptions, marketplace split-payments — plus the provider protocol, where Alif calls your server. First-class **Laravel** support.

> Works in plain PHP and any framework. Laravel gets auto-discovery, a config file and a facade for free.

Based on the official documentation: <https://docs.acquiring.alif.tj> and <https://alifcapital.github.io>

---

## Features

- **Standard payments** — Korti Milli, Alif wallet, Salom installments, cash invoices, Visa/Mastercard
- **Tokenization** — bind a card, wallet or phone account, then charge it, look it up and unbind it
- **Subscriptions** — regular charges from a bound payment method on a preset or custom schedule
- **Marketplace** — split a single payment between multiple sellers, hold & confirm delivery, Salom conditions
- **Currency** — the terminal's currency and its rate to the somoni
- **Provider endpoint** — the other direction: handle the `check` / `pay` / `status` requests Alif sends you
- **HMAC SHA256 signing** — the exact double-HMAC scheme Alif requires, done for you
- **Webhooks** — typed callbacks for payments, confirmations, cancellations and tokenization, each verified with the formula its event calls for
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

The test environment has test cards for trying scenarios without moving real money; Alif hands them out with the test terminal (the docs do not list them).

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
| `info` with tax details | `info` | body `info_hash` |
| Tokenization binding | `terminal_id + phone + gate` | body `token` |
| Status check | `terminal_id + orderId` | body `token` |
| Cancel | `terminal_id + transaction_id + amount` | body `token` |
| Confirm delivery | `terminal_id + transaction_id + amount` | body `token` |
| Confirm VSA/MCR delivery | `terminal_id + parent_transaction_id` | body `token` |
| Charge a bound token | `terminal_id + order_id + amount + token` | `hash` header |
| Tokenization status | `terminal_id + request_id` | `hash` header |
| Remove a token | `terminal_id + token` | `hash` header |
| Currency and rate | `terminal_id + order_id` | body `token` |
| Salom conditions | `terminal_id` | `Token` header |
| Create a subscription | `terminal_id + order_id + amount + token_request_id` | body `hash` |
| Subscription status / cancel | `terminal_id + order_id` | body `hash` |

Token charges and tokenization lookups carry the signature in a `hash` header, and their body `token` is the UUID of a bound payment method instead. Subscriptions call the terminal `terminal_id` and put the signature in a body field named `hash`.

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
| `Gate::Visa` | `visa` | Visa, on Alif's form |
| `Gate::Mastercard` | `mastercard` | Mastercard, on Alif's form |
| `Gate::CybersourceCheckout` | `cybersource_checkout` | International cards on Cybersource's own form |

Older Alif examples spelled Visa and Mastercard `vsa` and `mcr`. The docs now use `visa` and `mastercard`, and so does the enum since 3.0. If your terminal was set up with the old names, send them raw:

```php
PaymentRequest::make('ORDER_1', '100.00')->gateValue('vsa');
```

`gateValue()` takes any lowercase gate. What the gateway does with a value it does not know is not documented, so use one only when Alif gave it to you. The docs mention Google Pay, Apple Pay and the mobile operators as supported methods but publish no payment gate value for them.

### Tax details in `info`

When `info` carries tax details, the docs ask for its signature in `info_hash`. Korti Milli only:

```php
PaymentRequest::make('ORDER_1', '100.00')
    ->gate(Gate::KortiMilli)
    ->info('ИНН 123456789, НДС 0%')
    ->signInfo();
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

Cancellation is full-only, works on `ok` payments of Korti Milli, Alif Wallet and Salom, and Salom only within 14 days. A 200 means the request was accepted; the outcome arrives as a `cancel` callback, and the payment then reads `canceled`.

### Currency and rate

If the terminal is not in somoni, the payer still sees somoni on the form. Look the rate up to show the amount in advance:

```php
$rates = $alif->payments()->currency('ORDER_123456');

$rates->currency;          // "USD" — the terminal's currency
$rates->rate;              // 10.95 — to the somoni
$rates->rateFor('EUR');    // 11.80
$rates->toSomoni('100.50'); // "1100.48"
```

The rate refreshes every ten minutes; a somoni terminal always reports 1.

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

Available gates: `KortiMilli`, `Wallet`, `Salom`, `Tcell`, `Megafon`, `Babilon`, `ZetMobile`, `Visa`, `Mastercard`. Visa and Mastercard are confirmed by 3-D Secure at the card's bank, the rest by a one-time code. `Procard` is kept for older terminals and is deprecated.

`clientId()` is required — the gateway refuses a binding without it, and a subscription must later name the same value.

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

A declined token charge answers HTTP **203**. The SDK raises it as an `ApiException` like any other failure.

---

## Subscriptions

A fixed amount charged on a schedule from a payment method bound earlier. Supported for Korti Milli, Alif mobi and the mobile operators; not for Salom or international cards.

```php
use TexHub\AlifPay\Enums\IntervalUnit;
use TexHub\AlifPay\Enums\SubscriptionPeriod;
use TexHub\AlifPay\Requests\SubscriptionRequest;

$created = $alif->subscriptions()->create(
    SubscriptionRequest::make(
        orderId: 'SUB_ORDER_123',
        tokenRequestId: '45781',        // Payload.transactionId of the approved tokenization callback
        partnerClientId: 'client_12345', // the clientID used for that binding
        amount: '99.90',
    )
        ->period(SubscriptionPeriod::Monthly)       // or ->every(3, IntervalUnit::Month)
        ->endDate('2027-01-01T00:00:00Z')           // optional
        ->callbackUrl('https://shop.tj/alif/callback')
);

$created->subscriptionId;   // 1024
$created->status;           // SubscriptionStatus::Accepted

$details = $alif->subscriptions()->status('SUB_ORDER_123');
$details->status?->isActive();

$alif->subscriptions()->cancel('SUB_ORDER_123');
```

The schedule is set exactly once — a preset period or a custom interval (day up to 90, week up to 52, month up to 24, year up to 3). A new subscription is `accepted` and becomes `approved` only when its first charge succeeds; if that charge fails it ends `failed` with no retry. Every charge arrives as an ordinary payment callback (`event: payment`, `info: "Оплата по подписке"`). Cancelling stops future charges only; refund past ones with `payments()->cancel()`.

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

Salom conditions per seller terminal, to pick a split's `condition_id` before the payment:

```php
foreach ($alif->marketplace()->salomConditions() as $terminal) {
    $terminal->terminalId;                 // "200001"
    foreach ($terminal->conditions as $condition) {
        $condition->id;                    // 12 — goes into splitTo(..., conditionId: 12)
        $condition->displayName;           // "Рассрочка на 6 месяцев"
    }
}

$alif->marketplace()->currency('MP_ORDER_123');  // same as payments()->currency()
```

---

## Webhooks (callbacks)

Alif sends a `POST` to your `callback_url` on every status change. Answer exactly **HTTP 200**: anything else (or no answer within 20 seconds) is retried, five times about ten seconds apart and then hourly, up to fifteen attempts. The same callback can arrive more than once, so keep the handler idempotent.

### Payment callback

Standard payments, marketplace payments and subscription charges all arrive as a payment callback with `"event": "payment"`.

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

For marketplace, `$callback->subTransactions` holds the per-partner breakdown and `$callback->isMarketplace()` is `true`. `$callback->event` and `$callback->info` are there too.

### Confirmation and cancellation events

A delivery confirmation (`event: confirm`) and a cancellation (`event: cancel`) post a second shape to the same URL — one transaction, no `orderId`. It is signed with a **different** formula, `terminal_id . transaction_id . status`. Let the handler pick the shape and the formula:

```php
use TexHub\AlifPay\Webhook\DeliveryCallback;
use TexHub\AlifPay\Webhook\PaymentCallback;

$callback = $alif->webhooks()->callback(file_get_contents('php://input'));

if (! $alif->webhooks()->verifyCallback($callback)) {
    http_response_code(403);
    exit;
}

match (true) {
    $callback instanceof PaymentCallback  => handlePayment($callback),
    $callback instanceof DeliveryCallback => handleDelivery($callback),
};
```

A `DeliveryCallback` carries `terminalId`, `transactionId`, `status`, `event`, `token` and, on a marketplace, `parentTransactionId` and `parentStatus`. The cancellation of a standard payment has no parent fields (`isSubTransaction()` is `false`).

### Tokenization callback

> The tokenization callback has a **different** structure: `Code`, `Message` and `Payload` at the root, in PascalCase. `transaction_type` comes without the `tokenization_` prefix (`$callback->method()` gives it either way).

```php
$callback = $alif->webhooks()->tokenizationCallback(file_get_contents('php://input'));

if ($callback->isSuccessful()) {
    saveToken($callback->orderId, $callback->token); // store for repeat charges
}

http_response_code(200);
```

### Verifying what Alif sends back

Alif signs its own answers with the same HMAC scheme. Payment callbacks and status lookups are signed over `orderId . status . transactionId`; confirmation and cancellation callbacks over `terminal_id . transaction_id . status`:

```php
$alif->webhooks()->verifyCallback($callback);                    // picks the formula by event
$alif->webhooks()->verifyPaymentCallback($callback);             // payment callbacks
$alif->webhooks()->verifyDeliveryCallback($callback);            // confirm / cancel callbacks
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
    $e->apiCode;        // 203, 208, 400, 401, 404, 500
    $e->apiMessage;     // human-readable message (RU)
    $e->isDuplicate();  // true for code 208
    $e->isRetryable();  // true for 500 only
} catch (TransportException $e) {
    // network/connection failure
}
```

| Code | Meaning | Retry |
|------|--------------------|-------|
| 200 | Success | — |
| 202 | Accepted for processing (subscription created) | — |
| 203 | Token charge declined by the gateway — a failure | No |
| 208 | Duplicate order_id | No |
| 400 | Validation error | No |
| 401 | Bad signature or unknown terminal | No |
| 404 | Not found | No |
| 500 | Internal error | Yes |

403 is not in the current docs; it still parses as `ResponseCode::Forbidden` in case an older gateway sends it.

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
use TexHub\AlifPay\Webhook\PaymentCallback;

public function callback(Request $request)
{
    $callback = AlifPay::webhooks()->callback($request->getContent());

    if (! AlifPay::webhooks()->verifyCallback($callback)) {
        return response('', 403);
    }

    if ($callback instanceof PaymentCallback && $callback->status === PaymentStatus::Ok) {
        $status = AlifPay::payments()->checkStatus($callback->orderId);

        if (AlifPay::webhooks()->verifyStatusResponse($status) && $status->get('status') === 'ok') {
            Order::where('reference', $callback->orderId)->update(['status' => 'paid']);
        }
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
// ... assert on $transport->lastBody / lastHeaders / lastUrl / lastMethod
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
├── AlifPay.php              # entry point — payments()/tokenization()/subscriptions()/marketplace()/webhooks()/provider()
├── Config.php               # immutable configuration
├── Signature.php            # HMAC SHA256 double-hash signer
├── Enums/                   # Environment, Gate, PaymentStatus, TokenizationState, ProviderCode, …
├── Http/                    # Transport interface (POST and GET), CurlTransport, Response
├── Requests/                # PaymentRequest, TokenChargeRequest, SubscriptionRequest, MarketplaceRequest, …
├── Responses/               # CurrencyRates, TerminalConditions, SubscriptionCreated, SubscriptionDetails
├── Clients/                 # PaymentClient, TokenizationClient, SubscriptionClient, MarketplaceClient
├── Webhook/                 # callback DTOs + WebhookHandler
├── Provider/                # the endpoint Alif calls: check / pay / status
├── Exceptions/              # ApiException, TransportException, …
└── Laravel/                 # ServiceProvider + Facade
```

Developer documentation — architecture, every signing string, per-area notes and the open questions in Alif's own docs — lives in [`docs/dev/`](docs/dev/README.md).

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md). Version 3.0.0 renames the Visa and Mastercard gate values to `visa` / `mastercard`, adds `get()` to the `Transport` interface and requires `clientId()` for tokenization; 2.0.0 changed the marketplace split field. Read it before upgrading.

---

## License

MIT © TexHub Pro — built by Mahmudi Shodmehr.
