# Signing

Every request is authorized with a token derived from the terminal password. The password is never
sent.

```
secret = HMAC_SHA256(terminal_password, terminal_id)
token  = HMAC_SHA256(dataToSign, secret)
```

`Signature` holds both steps. `Signature::concat()` builds the signing string, skipping nulls, so
a missing optional field cannot silently shift the order of the rest.

The full table of signing strings per endpoint is in `../architecture.md`. Two rules keep it
honest:

- **The signed amount and the sent amount are the same string.** `Requests\Amount` produces
  `number_format($amount, 2, '.', '')` once, and both the body and the signing string use that
  value. `/pay/token` sends the amount as a JSON number and signs its two-decimal string form —
  the one place where the two representations differ on purpose.
- **The signed phone is the normalized phone.** `TokenizationRequest` rewrites whatever the caller
  passed into `+992XXXXXXXXX` before either signing or sending it.

## Verifying what comes back

Alif signs its answers with the same scheme over `orderId + status + transactionId`. Both the
acquiring documentation ("Верификация токена ответа") and the WebCheckout protocol specification
state this, and the WebCheckout page shows a worked example: `"12345678ok92938922"`.

Because the status is inside the signed string, a replayed callback that claims a different
outcome does not verify. That is the property the check is there for.

`Signature::verify()` uses `hash_equals`; nothing in this package compares tokens with `===`.

## Provider endpoint

The provider protocol does not use HMAC at all — it authorizes with HTTP Basic over the agreed
login and password. `Provider\ProviderServer::authorize()` compares with `hash_equals` on the
decoded `login:password` pair, so a wrong login and a wrong password cost the same time.
