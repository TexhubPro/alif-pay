# Payments

`Clients\PaymentClient`, built through `AlifPay::payments()`.

## initiate()

`POST /v2/` with the gate in both the header and the body. `Requests\PaymentRequest` builds the
domain fields; the client adds `key` and `token`.

Gates live in `Enums\Gate`: `korti_milli` (default), `wallet`, `salom`, `invoice`, `vsa`, `mcr`,
`cybersource_checkout`. `PaymentRequest::gateValue()` takes a raw string for methods the published
table does not name — Alif's list of supported methods is longer than its list of gate values, and
an unknown value is answered with the standard payment form rather than refused. The value is
still checked against `[a-z0-9_]+` so nothing arbitrary reaches the header.

Salom and cash invoices want line items (`Requests\InvoiceItem`), which go out nested as
`invoices.invoices[]`. Cash invoices also take a `deadline` in RFC3339.

## checkStatus()

`POST /checktxn`. The field is `orderId`, camel case — this endpoint is the one place in the API
that spells it that way, and anything else is rejected as an invalid order identifier.

The answer is the transaction itself and carries no `code`, so it is not judged by one. Verify the
`token` it carries with `WebhookHandler::verifyStatusResponse()` before acting on the status.

## cancel()

`POST /cancel/standard`. Full cancellation only — the amount must equal the transaction's own, and
Alif answers "Частичная отмена не поддерживается" otherwise. Salom payments can only be cancelled
within 14 days. `reason` is optional and is omitted from the body when empty.

Only `ok` (and `settled`) transactions can be cancelled, and only Korti Milli, Alif Wallet and
Salom are cancellable at all.
