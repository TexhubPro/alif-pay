# Signing

Every request is authorized with a token derived from the terminal password. The password is never
sent.

```
secret = HMAC_SHA256(terminal_password, terminal_id)
token  = HMAC_SHA256(dataToSign, secret)
```

`Signature` holds both steps. `Signature::concat()` builds the signing string, skipping nulls, so
a missing optional field cannot silently shift the order of the rest.

The full table of signing strings per endpoint is in `../architecture.md`. Three rules keep it
honest:

- **The signed amount and the sent amount are the same string.** `Requests\Amount` produces
  `number_format($amount, 2, '.', '')` once, and both the body and the signing string use that
  value. `/pay/token` and `/subscription/create` send the amount as a JSON number and sign its
  two-decimal string form — the places where the two representations differ on purpose.
- **`info_hash` signs `info` alone**, with the same pre-hashed secret, when `signInfo()` asks for
  it (Korti Milli, tax details).
- **The signed phone is the normalized phone.** `TokenizationRequest` rewrites whatever the caller
  passed into `+992XXXXXXXXX` before either signing or sending it.

## Verifying what comes back

Alif signs its answers with the same scheme. Payment callbacks and status lookups use
`orderId + status + transactionId` — the acquiring documentation ("Верификация токена ответа") and
the WebCheckout protocol specification both state this, and the WebCheckout page shows a worked
example: `"12345678ok92938922"`. Confirmation and cancellation callbacks use
`terminal_id + transaction_id + status` ("Формулы различаются"); `WebhookHandler::verifyCallback()`
chooses by event.

Because the status is inside the signed string, a replayed callback that claims a different
outcome does not verify. That is the property the check is there for.

`Signature::verify()` uses `hash_equals`; nothing in this package compares tokens with `===`.

## Provider endpoint

The provider protocol does not use HMAC at all — it authorizes with HTTP Basic over the agreed
login and password. `Provider\ProviderServer::authorize()` compares with `hash_equals` on the
decoded `login:password` pair, so a wrong login and a wrong password cost the same time.
