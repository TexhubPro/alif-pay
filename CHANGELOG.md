# Changelog

All notable changes to `texhub/alif-pay`.

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
