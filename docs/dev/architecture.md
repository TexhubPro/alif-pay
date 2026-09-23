# Architecture

## What this package is

A framework-agnostic PHP client for Alif's payment protocols, with Laravel wiring on top. It
speaks two directions:

- **Acquiring** — we call Alif: start a payment, bind and charge a payment method, split a payment
  between sellers, look up a status, cancel. Documented at <https://docs.acquiring.alif.tj>.
- **Provider** — Alif calls us: `check`, `pay`, `status` against our endpoint, authorized with HTTP
  Basic. Documented at <https://alifcapital.github.io/providers>.

Everything else on <https://alifcapital.github.io> (merchants/EMV QR, invoices, agents) is a
different Alif product and is deliberately out of scope.

## Areas

| Area | Path | Section doc |
|------|------|-------------|
| Entry point | `src/AlifPay.php` | `architecture.md` (this file) |
| Configuration | `src/Config.php`, `config/alif-pay.php` | `sections/laravel.md` |
| Signing | `src/Signature.php` | `sections/signing.md` |
| HTTP | `src/Http/` | `sections/transport.md` |
| Payments | `src/Clients/PaymentClient.php`, `src/Requests/PaymentRequest.php` | `sections/payments.md` |
| Tokenization | `src/Clients/TokenizationClient.php`, `src/Requests/Tokenization*.php`, `src/Requests/TokenChargeRequest.php` | `sections/tokenization.md` |
| Marketplace | `src/Clients/MarketplaceClient.php`, `src/Requests/MarketplaceRequest.php`, `src/Requests/TerminalSplit.php`, `src/Requests/DeliveryConfirmation.php` | `sections/marketplace.md` |
| Webhooks | `src/Webhook/` | `sections/webhooks.md` |
| Provider endpoint | `src/Provider/` | `sections/provider.md` |
| Enums | `src/Enums/` | the section that owns each one |
| Exceptions | `src/Exceptions/` | `sections/transport.md` |
| Laravel | `src/Laravel/` | `sections/laravel.md` |
| Tests | `tests/` | `sections/testing.md` |

## Request flow (acquiring)

```
AlifPay::payments()            a lazily built client, sharing one Config/Transport/Signature
  └─ PaymentClient::initiate(PaymentRequest)
       ├─ PaymentRequest::toArray()   domain fields + config defaults, validated here
       ├─ Signature::generate()       the signing string is built by the client, never the request
       └─ AbstractClient::send()      POST, decode, raise ApiException on failure
            └─ Transport::post()      CurlTransport by default, FakeTransport in tests
```

The split of responsibility is deliberate: a `Requests\*` object knows the shape of the body and
nothing about credentials; a `Clients\*` object knows which fields are signed and in what order.
That is why `key` and `token` are injected by the client at the last moment.

## Endpoints

| Endpoint | Client method | Signed as | Signature travels in |
|----------|---------------|-----------|----------------------|
| `POST /v2/` | `payments()->initiate()` | `key + order_id + amount + callback_url` | body `token` |
| `POST /v2/` (`isMarketPlace`) | `marketplace()->initiate()` | `key + order_id + amount + callback_url` | body `token` |
| `POST /v2/` (`tokenization_*`) | `tokenization()->initiate()` | `key + phone + gate` | body `data.token` |
| `POST /checktxn` | `payments()->checkStatus()`, `marketplace()->checkStatus()` | `key + orderId` | body `token` |
| `POST /cancel/standard` | `payments()->cancel()` | `key + transaction_id + amount` | body `token` |
| `POST /cancel/marketplace` | `marketplace()->cancel()` | `key + transaction_id + amount` | body `token` |
| `POST /confirm-delivery` | `marketplace()->confirmDelivery()` | `key + transaction_id + amount` | body `token` |
| `POST /confirm-vsa-and-mcr-delivery` | `marketplace()->confirmVsaMcrDelivery()` | `key + parent_transaction_id` | body `token` |
| `POST /pay/token` | `tokenization()->charge()` | `key + order_id + amount + token` | header `hash` |
| `POST /tokenization/status` | `tokenization()->status()` | `key + request_id` | header `hash` |
| `POST /tokenization/remove` | `tokenization()->remove()` | `key + token` | header `hash` |

The older endpoints carry the signature in the body under `token`; the three newest carry it in a
`hash` header, and their body `token` is the UUID of a bound payment method instead. Reading a
`token` field therefore means nothing without knowing which endpoint it came from.

## Inbound directions

| Arrives at | Parsed by | Verifiable |
|------------|-----------|------------|
| Payment / marketplace callback | `Webhook\PaymentCallback` | yes, `orderId + status + transactionId` |
| Delivery confirm / cancel event | `Webhook\DeliveryCallback` | no token in the payload |
| Tokenization callback | `Webhook\TokenizationCallback` | no token over the payload |
| Provider `check` / `pay` / `status` | `Provider\ProviderRequest` | HTTP Basic, constant-time |

`WebhookHandler::callback()` picks between the first two by looking for `event` /
`parent_transaction_id`, since all three land on the same URL.

## Conventions

- `final` classes, `declare(strict_types=1)`, readonly properties, no inline comments.
- Every value that is both signed and sent is normalized once, in one place (`Requests\Amount`,
  `TokenizationRequest::normalizePhone()`), so the two can never drift apart.
- Validation that Alif would answer with a 400 happens locally first, before a request is spent.
- Nothing is logged by this package, so a terminal password cannot leak through it.
