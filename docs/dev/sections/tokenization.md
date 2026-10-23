# Tokenization

`Clients\TokenizationClient`, built through `AlifPay::tokenization()`. Four endpoints: bind,
charge, look up, unbind.

## initiate() — bind a payment method

`POST /v2/`, gate in the header, body wrapped in a `data` object with camelCase keys
(`orderId`, `callbackURL`, `returnURL`, `clientID`). Signing string: `key + phone + gate`.

Gates are in `Enums\TokenizationGate`, all prefixed `tokenization_`: korti_milli, wallet, salom,
tcell, megafon, babilon, zetmobile, visa, mastercard. Visa and Mastercard are confirmed by 3-D
Secure at the card's bank, the rest by a one-time code. `procard` is not in the current docs; it
is kept, deprecated, for older terminals.

The phone must be `+992XXXXXXXXX`. `TokenizationRequest` normalizes whatever it is given — bare
nine digits, spaces, dashes — before signing it, because the phone is part of the signing string
and the sent value and the signed value must match.

`clientID` is required (validation step 6, "Неправильный идентификатор клиента"), and a
subscription later has to name the same value as `partner_client_id`. Since 3.0.0
`TokenizationRequest::toArray()` refuses to build without it.

## charge() — take money from a bound method

`POST /pay/token`. This is the point of tokenization and the reason the package exists in this
shape: `initiate()` alone can only bind.

The signature goes in a `hash` header, not the body, and the body `token` is the UUID handed over
by the tokenization callback. Signing string: `key + order_id + amount + token`, with the amount in
its two-decimal string form even though the body sends it as a number.

`callback_url` is required; `return_url` is not. A declined charge answers HTTP 203 — a 2xx that
is still a failure; `AbstractClient` raises it as `ApiException` (`ResponseCode::Declined`). For
Salom, Visa/Mastercard and terminals with mandatory 3-D Secure the answer carries `payload.url`,
where the customer must be sent. Adding splits (`splitTo()` / `addSplit()`) turns
it into a marketplace charge through `mpTerminalInfo`, and the splits must add up to the amount.

## status() — look up a binding

`POST /tokenization/status`, signing string `key + request_id`, `hash` header. `request_id` is the
`orderId` used when binding. The body still carries a `token` key, sent empty — Alif's own example
does that.

The answer nests the result under `payload`. `Enums\TokenizationState` types the `status` string:
`accepted`, `approved`, `duplicate`, `failed`, `removed`.

## remove() — unbind

`POST /tokenization/remove`, signing string `key + token` — the request id is sent but not signed.
After removal the token cannot be charged.

## The callback

`Webhook\TokenizationCallback` parses a shape unlike any payment callback: the result code sits at
the root (`Code`: `1` success, `2` duplicate, `9` rejected — `Enums\TokenizationStatus`) and the
data sits under `Payload`, both in PascalCase (lowercase still read). `transaction_type` comes
without the `tokenization_` prefix; `method()` normalizes it. It carries no token over its own
contents, so there is nothing to verify; confirm with `status()` if it matters.
