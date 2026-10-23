# Developer documentation — texhub/alif-pay

Living documentation for the Alif Acquiring (WebCheckout) PHP SDK. It is the memory of this
codebase: read it before touching the code, update it in the same change that touches source,
config or tests.

## Layout

| File | What it holds |
|------|---------------|
| `architecture.md` | The map: every area of the package, where it lives, how a request flows |
| `changelog.md` | Dated entries — what changed, why, which section docs moved with it |
| `sections/signing.md` | The HMAC scheme, every signing string, where each one is built |
| `sections/transport.md` | `Transport`, `CurlTransport`, response decoding, error mapping |
| `sections/payments.md` | Standard payments: initiate, status, cancel, currency |
| `sections/tokenization.md` | Binding a payment method, charging it, status, removal |
| `sections/subscriptions.md` | Regular charges from a bound method: create, status, cancel |
| `sections/marketplace.md` | Split payments, delivery confirmation, cancellation, Salom conditions |
| `sections/webhooks.md` | Callback shapes, parsing, verification, what verification does not prove |
| `sections/laravel.md` | Service provider, facade, config publishing |
| `sections/testing.md` | Test layout, `FakeTransport`, what each suite covers |

## Upstream documentation

The gateway documentation lives at <https://docs.acquiring.alif.tj> and has exactly four pages:
`intro`, `payments`, `tokenization`, `marketplace`. There is no version marker on it, so the only
way to notice a change is to re-read it. Every endpoint this SDK speaks to is listed in
`architecture.md` with the page it came from.

Where the upstream documentation contradicts itself, the contradiction is recorded in the matching
section doc rather than silently resolved in code.

## Working rules for this package

- The amount that goes into the signing string and the amount that goes into the request body must
  be the same string. All amount handling goes through `Requests\Amount` for that reason.
- No endpoint is added without a test that asserts the exact signing string it sends.
- Credentials never appear in code, tests or fixtures. Tests use the placeholder terminal
  `123456` / `super-secret`.
