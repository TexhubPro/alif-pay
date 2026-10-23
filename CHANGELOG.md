# Changelog

All notable changes to `texhub/alif-pay`.

## 3.0.0 — 2026-10-24

Aligned with the documentation as published on 2026-10-24 (<https://docs.acquiring.alif.tj>:
intro, payments, marketplace, tokenization, subscription).

### Breaking

- `Gate::Visa` is now `visa` and `Gate::Mastercard` is `mastercard`, the names the docs use.
  Terminals still set up with the old `vsa` / `mcr` can send them with `gateValue('vsa')`.
- `Transport` gained `get()` (needed by `GET /conditions/{key}`). A custom transport must
  implement it.
- `TokenizationRequest` refuses to build without `clientId()`; the gateway rejects such a binding
  anyway.
- `ResponseCode::NotFound->isRetryable()` is now `false`; the docs mark only 500 as worth retrying.
- `Gate::requiresInvoices()` is true for Salom only; a cash invoice is documented without goods.

### Fixed

- Standard payment callbacks parsed as `DeliveryCallback`. Every payment callback now carries
  `"event": "payment"`, and `webhooks()->callback()` took any `event` as a delivery event. Only
  `confirm` / `cancel` (or a parent transaction) select that shape now.
- Tokenization callbacks parsed empty. Alif sends `Code`, `Message`, `ReasonCode` and `Payload` in
  PascalCase; they are read first, lowercase second.
- Confirmation and cancellation callbacks were described as unsigned. They carry a `token` over
  `terminal_id . transaction_id . status`; `DeliveryCallback::$token` and
  `webhooks()->verifyDeliveryCallback()` check it.

### Added

- `subscriptions()` — `create()`, `status()`, `cancel()` for `POST /subscription/*`, with
  `SubscriptionRequest`, `SubscriptionStatus`, `SubscriptionPeriod`, `IntervalUnit` and typed
  `SubscriptionCreated` / `SubscriptionDetails`.
- `payments()->currency()` and `marketplace()->currency()` — `POST /currency`, typed as
  `CurrencyRates` with `rateFor()` and `toSomoni()`.
- `marketplace()->salomConditions()` — `GET /conditions/{key}`, typed as `TerminalConditions`.
- `PaymentRequest::signInfo()` — sends `info_hash` for tax details on Korti Milli.
- `TokenizationGate::Visa`, `TokenizationGate::Mastercard`.
- `CallbackEvent::Payment`, `CallbackEvent::Tokenize`; `PaymentCallback::$event`, `$info`.
- `webhooks()->verifyCallback()` picks the formula by event; `assertValidDeliveryCallback()`.
- `TokenizationCallback::method()` — the bound method without the `tokenization_` prefix.
- `ResponseCode::Accepted` (202) and `ResponseCode::Declined` (203, a declined token charge).

### Deprecated

- `TokenizationGate::Procard` — not in the current docs; use `Visa` / `Mastercard`.

### Notes

- `PaymentStatus::Settled`, `Approved` and `PartialApproved`, and `ResponseCode::Forbidden`, are
  not in the current docs. They stay so older answers still parse.
- The docs list Google Pay, Apple Pay and the mobile operators as supported, but publish no
  payment `gate` value for them. The README no longer claims an unknown gate falls back to the
  standard form; that is not documented.

## 2.0.0 — 2026-09-23

Rebuilt against the current Alif documentation: <https://docs.acquiring.alif.tj> and
<https://alifcapital.github.io>.

### Breaking

- Marketplace splits are sent as `mpTerminalInfo`, not `mp_terminal_amounts`. Splits now carry
  `condition_id` and per-seller `invoices`.
- `Requests\TerminalAmount` is replaced by `Requests\TerminalSplit`, and
  `MarketplaceRequest::addTerminal()` by `addSplit()`. `splitTo()` takes an optional Salom
  condition as its third argument.
- `MarketplaceClient::confirmVsaMcrDelivery()` requires the child transactions it confirms,
  passed as `DeliveryConfirmation` objects.
- `PaymentRequest::getGate()` returns a string rather than a `Gate`, so that gates outside the
  published table can be used.

### Added

- `tokenization()->charge()` — charge a bound payment method (`POST /pay/token`), with optional
  marketplace splits.
- `tokenization()->status()` — look up a binding (`POST /tokenization/status`).
- `tokenization()->remove()` — unbind a payment method (`POST /tokenization/remove`).
- `provider()` — the provider protocol from <https://alifcapital.github.io/providers>, where Alif
  calls your endpoint with `check`, `pay` and `status`: Basic authorization, typed requests,
  response builders and the full result-code table.
- `Webhook\DeliveryCallback` and `webhooks()->callback()` for marketplace delivery confirmation
  and cancellation events, which previously parsed as empty payment callbacks.
- `cancel()` accepts the optional `reason` on both the payment and the marketplace client.
- `Enums\TokenizationState`, `Enums\CallbackEvent`, `Enums\ProviderAction`, `Enums\ProviderCode`.
- `PaymentStatus::Settled`.
- `webhooks()->assertValidStatusResponse()`.
- `PaymentRequest::gateValue()` for gates the published table does not name yet.

### Fixed

- `checkStatus()` sends `orderId`, not `order_id` — the camel-case spelling this endpoint requires
  — and no longer judges the answer by a `code` field that a successful lookup does not contain.
- Callback and status tokens are verified over `orderId + status + transactionId`, the documented
  signing string. A callback replayed with a different outcome no longer verifies.
- Tokenization phone numbers are normalized to `+992XXXXXXXXX` before being signed and sent.
- `MarketplaceClient::checkStatus()` no longer sends an undocumented `isMarketPlace` header.
- The documentation no longer claims the gateway always answers HTTP 200; it mirrors the business
  code, and status lookups carry no code at all.

### Security

- `CurlTransport` sets certificate verification, an HTTPS-only protocol allowlist and refuses to
  follow redirects, so a signed body cannot be replayed to a host named by a redirect.
- Provider authorization compares credentials in constant time.

## 1.0.0 — 2026-06-01

First release: payments, tokenization binding, marketplace split-payments, webhooks, Laravel
integration.
