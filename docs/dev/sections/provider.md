# Provider endpoint

`Provider\ProviderServer`, built through `AlifPay::provider()`. This is the other direction: Alif
calls our server so its customers can pay for our service at Alif's tills and in its app.

Specification: <https://alifcapital.github.io/providers>. It shares nothing with acquiring — no
HMAC, no terminal id, a separate login and password, its own set of result codes.

## Credentials

`provider_login` / `provider_password` in the config, `ALIF_PAY_PROVIDER_LOGIN` /
`ALIF_PAY_PROVIDER_PASSWORD` in the environment. They are unrelated to the terminal credentials,
so `provider()` throws a `ConfigurationException` until they are set rather than quietly reusing
the wrong ones.

Authorization is `Authorization: BASE64("login:password")`, compared with `hash_equals`.

## The three requests

| `action` | Meaning | Answer with |
|----------|---------|-------------|
| `check` | Does this subscriber exist and can the payment be taken? | `302` found, or a failure code |
| `pay` | Credit the subscriber | `200` + `response_id`, or `108` for a duplicate |
| `status` | What happened to payment `id`? | `200` / `201` / `203` / `104` |

`id` is the payment identifier in Alif's system and is the idempotency key: a repeated `pay` with
an id already credited must return the stored outcome, never credit twice.

`ProviderResponse` builds the body — `accountFound()`, `paid()`, `duplicate()`, `status()`,
`code()` — and `with()` attaches the extra settlement fields (`bank_fee`, `payments`,
`partner_fee`) that marketplace agreements add.

## Codes and retries

`Enums\ProviderCode` carries every documented code and `isFatal()`. Fatal means Alif gives up on
the payment; non-fatal means it retries at growing intervals for up to 24 hours. `201` (in
progress), `107` (transaction exists), `305` (insufficient funds) and `520` (unknown) are the
non-fatal ones. Returning a fatal code for a temporary problem throws the payment away.

Alif reads the body, not the HTTP status. `ProviderResponse::httpStatus()` offers a sensible one
anyway.

## Requirements Alif places on the endpoint

HTTPS, JSON in and out, UTF-8, an answer within 60 seconds, and concurrency once traffic passes
roughly 10 payments a minute.
