# Changelog (developer log)

Newest first. Product-facing release notes live in `../../CHANGELOG.md`.

## 2026-10-24 — 3.0.0, aligned with the docs again

**Why.** TexHub Billing started paying invoices through Alif and its webhook would have dropped
every payment: a fresh read of <https://docs.acquiring.alif.tj> (intro, payments, marketplace,
tokenization, subscription; downloaded 2026-10-24) showed the payment callback now carries
`"event": "payment"`, which 2.x took for a delivery event.

**Callbacks.** `DeliveryCallback::matches()` keys on `confirm` / `cancel` only.
`CallbackEvent` gained `Payment` and `Tokenize`; `PaymentCallback` gained `event` and `info`.
Confirm / cancel callbacks are signed (`terminal_id + transaction_id + status`) — 2.x said they
were not; `DeliveryCallback::$token`, `verifyDeliveryCallback()`, `verifyCallback()` added.
Tokenization callbacks are PascalCase; read first, lowercase second.
→ `sections/webhooks.md`, `sections/tokenization.md`, `architecture.md`

**Gates — breaking.** `Gate::Visa` / `Gate::Mastercard` are `visa` / `mastercard` as documented
(Alif's SDK repo still says `vsa` / `mcr`; the docs win, the old names stay reachable through
`gateValue()`). `TokenizationGate` gained `Visa` / `Mastercard`; `Procard` deprecated. The claim
that an unknown gate falls back to the standard form was undocumented and is gone.
`requiresInvoices()` is Salom only. `info_hash` support through `PaymentRequest::signInfo()`.
→ `sections/payments.md`, `sections/tokenization.md`

**New endpoints.** `POST /currency` (`payments()` / `marketplace()->currency()`),
`GET /conditions/{key}` (`marketplace()->salomConditions()`), the Subscriptions API
(`subscriptions()->create/status/cancel`). Typed answers live in the new `src/Responses/`.
`Transport` gained `get()` — breaking for custom transports.
→ `sections/subscriptions.md` (new), `sections/marketplace.md`, `sections/transport.md`

**Codes.** 404 is no longer retryable (only 500 is, per /intro); 202 and 203 added; clients accept
extra OK codes per call. `TokenizationRequest` requires `clientId()`.
→ `sections/transport.md`

**Kept, flagged.** `settled`, `approved`, `partial_approved` and 403 are not in the current docs;
kept so older answers parse. Test cards and Google Pay / Apple Pay / operator payment gates are not
documented.

Tests: 91 (was 67).

## 2026-09-23 — rebuilt against the current Alif documentation

**Why.** The package was written against an earlier reading of the acquiring documentation and had
drifted. A full re-read of <https://docs.acquiring.alif.tj> (intro, payments, tokenization,
marketplace) and of <https://alifcapital.github.io> (webcheckout, providers) turned up one broken
request, one request that could never succeed, three endpoints that were missing outright and a
callback shape that was being thrown away.

**Marketplace splits — breaking.** `mp_terminal_amounts` was replaced by `mpTerminalInfo`, which is
what the gateway documents now. Splits gained `condition_id` (Salom) and per-seller `invoices`.
`Requests\TerminalAmount` became `Requests\TerminalSplit`; `MarketplaceRequest::addTerminal()`
became `addSplit()`, and `splitTo()` gained a condition argument.
→ `sections/marketplace.md`

**Visa/Mastercard delivery confirmation.** `confirmVsaMcrDelivery()` now sends the required `extra`
array of child transactions; before, it sent only the parent and the gateway answered "Список
транзакций для подтверждения пуст". An empty call is refused locally.
→ `sections/marketplace.md`

**Tokenization gained its three missing endpoints:** `charge()` (`POST /pay/token`), `status()`
(`POST /tokenization/status`) and `remove()` (`POST /tokenization/remove`). All three sign into a
`hash` header rather than the body. Without them the package could bind a payment method but never
charge it.
→ `sections/tokenization.md`, `architecture.md`

**Marketplace event callbacks.** Added `Webhook\DeliveryCallback` and
`WebhookHandler::callback()`. A delivery confirmation or cancellation was previously parsed as a
payment callback and came out with empty fields, so the event was lost.
→ `sections/webhooks.md`

**Provider protocol — new area.** `src/Provider/` implements the endpoint Alif calls with `check`,
`pay` and `status`, with Basic authorization compared in constant time, typed requests, response
builders and the full code table with its fatal/non-fatal split. Reached through
`AlifPay::provider()` once `provider_login` / `provider_password` are configured.
→ `sections/provider.md`, `sections/laravel.md`

**Smaller corrections.**

- `cancel()` on both clients accepts the documented optional `reason`.
- `PaymentStatus::Settled` added; it is cancellable and counts as captured.
- `Enums\TokenizationState` types the payload status string (accepted / approved / duplicate /
  failed / removed); `TokenizationCallback::state()` exposes it.
- Tokenization phone numbers are normalized to `+992XXXXXXXXX` before being signed or sent.
- `orderId` length limits (40) are enforced where Alif documents them.
- `MarketplaceClient::checkStatus()` no longer sends an `isMarketPlace` header the documentation
  does not ask for.
- `assertValidStatusResponse()` added alongside the boolean check.
- The claim that "the gateway always answers HTTP 200" was removed from `ResponseCode`,
  `ApiException` and both READMEs. It answers with a status mirroring the business code; status
  lookups are the real exception, and they carry no code at all.
- `CurlTransport` sets certificate verification, an HTTPS-only protocol allowlist and
  `FOLLOWLOCATION => false` explicitly, and preserves zero fractions when encoding amounts.
- Inline comments were removed from the test suite to match the workspace rule.

**Docs.** `docs/dev/` was created in this change: it did not exist before. Section docs were
written for every area, not only the ones that moved, so the backfill is complete.

**Open question, deliberately not resolved in code.** Alif's marketplace page contradicts itself on
the invoice shape inside `mpTerminalInfo`: the request examples show
`{name, category, quantity, price}`, the parameter table `{amount, label, store_id}`. The package
follows the examples. This needs a live check on a test terminal.
