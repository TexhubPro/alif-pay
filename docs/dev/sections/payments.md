# Payments

`Clients\PaymentClient`, built through `AlifPay::payments()`.

## initiate()

`POST /v2/` with the gate in both the header and the body. `Requests\PaymentRequest` builds the
domain fields; the client adds `key` and `token`.

Gates live in `Enums\Gate`: `korti_milli` (default), `wallet`, `salom`, `invoice`, `visa`,
`mastercard`, `cybersource_checkout`. Since 3.0.0 Visa and Mastercard use the names the docs give;
older Alif examples (and the 2.x enum) said `vsa` / `mcr`. `PaymentRequest::gateValue()` takes a
raw string for those legacy names or for a value Alif hands out before documenting it. What the
gateway does with an unknown value is not documented — the 2.x note that it falls back to the
standard form had no source and was removed. The value is still checked against `[a-z0-9_]+` so
nothing arbitrary reaches the header.

Google Pay, Apple Pay and the mobile operators are listed as supported on /intro, but no payment
`gate` value is published for them (operators have only `tokenization_*` gates and appear as
`transaction_type` values).

Line items (`Requests\InvoiceItem`) go out nested as `invoices.invoices[]`. The docs make them
mandatory only where the terminal is set up to demand them and show them on Salom alone, so
`Gate::requiresInvoices()` is true for Salom only. Cash invoices take a `deadline` in RFC3339
(72 hours by default).

`info_hash`: when `info` carries tax details, the docs ask for `HMAC(info)` in `info_hash`, Korti
Milli only. `PaymentRequest::signInfo()` asks for it; `PaymentClient::initiate()` computes it with
the terminal password at send time, and the request refuses it without `info` or on another gate.

## checkStatus()

`POST /checktxn`. The field is `orderId`, camel case — this endpoint is the one place in the API
that spells it that way, and anything else is rejected as an invalid order identifier.

The answer is the transaction itself and carries no `code`, so it is not judged by one. Verify the
`token` it carries with `WebhookHandler::verifyStatusResponse()` before acting on the status.

## cancel()

`POST /cancel/standard`. Full cancellation only — the amount must equal the transaction's own, and
Alif answers "Частичная отмена не поддерживается" otherwise. Salom payments can only be cancelled
within 14 days. `reason` is optional and is omitted from the body when empty.

Only `ok` transactions can be cancelled, and only Korti Milli, Alif Wallet and Salom are
cancellable at all. The 200 answer only means the request was accepted: the result arrives as a
signed `cancel` callback (see `webhooks.md`) and the status then reads `canceled`.

## currency()

`POST /currency`, signing string `key + order_id`. Returns the terminal's currency and its rate to
the somoni, plus the other supported currencies, typed as `Responses\CurrencyRates`. The order id
only has to match what was signed. Shared with the marketplace client through
`AbstractClient::fetchCurrency()`; the rate refreshes every ten minutes.

## Statuses

Standard payments: `pending`, `ok`, `failed`, `canceled` (only after `ok` — the money was taken and
returned). `settled`, `approved` and `partial_approved` are not in the current docs and are kept in
`Enums\PaymentStatus` so older answers parse.
