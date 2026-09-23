# Laravel integration and configuration

## Configuration

`Config` is immutable and validates on construction: a blank terminal id or password, or a
timeout below one second, throws a `ConfigurationException` immediately rather than at the first
request.

`Config::fromArray()` maps a plain array — the Laravel config entry — onto it, accepting the
environment as a string (`test`, `production`, `prod`, `live`; anything unknown falls back to
test, which is the safe direction).

| Key | Env var | Notes |
|-----|---------|-------|
| `environment` | `ALIF_PAY_ENVIRONMENT` | `test` or `production` |
| `terminal_id` | `ALIF_PAY_TERMINAL_ID` | sent as `key` |
| `terminal_password` | `ALIF_PAY_TERMINAL_PASSWORD` | never sent, only hashed |
| `callback_url` | `ALIF_PAY_CALLBACK_URL` | default, overridable per request |
| `return_url` | `ALIF_PAY_RETURN_URL` | default, overridable per request |
| `timeout` | `ALIF_PAY_TIMEOUT` | seconds |
| `provider_login` | `ALIF_PAY_PROVIDER_LOGIN` | provider protocol only |
| `provider_password` | `ALIF_PAY_PROVIDER_PASSWORD` | provider protocol only |
| `base_url` | `ALIF_PAY_BASE_URL` | advanced override |

No value is hardcoded anywhere in `src/`; the base URLs live in `Enums\Environment` and can be
overridden per environment.

## Service provider

`Laravel\AlifPayServiceProvider` is auto-discovered and deferred. It binds `Config`, `AlifPay`
(aliased `alif-pay`), `WebhookHandler` and `ProviderServer` as singletons, and publishes the
config under the `alif-pay-config` tag.

`ProviderServer` resolves through `AlifPay::provider()`, so injecting it without provider
credentials configured throws rather than binding something half-built.

## Facade

`Laravel\AlifPay` resolves `alif-pay` and documents every accessor in its docblock, so
`AlifPay::payments()`, `AlifPay::webhooks()`, `AlifPay::provider()` stay typed in an IDE.
