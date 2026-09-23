# Changelog (developer log)

Newest first. Product-facing release notes live in `../../CHANGELOG.md`.

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
