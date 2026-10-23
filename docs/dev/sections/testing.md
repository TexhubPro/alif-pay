# Testing

```bash
composer install
composer test
```

PHPUnit 11 or 12, PHP 8.2+. No test touches the network.

## Layout

| Suite | Covers |
|-------|--------|
| `tests/Unit/AmountTest.php` | amount normalization and rejection |
| `tests/Unit/SignatureTest.php` | the double HMAC against a known value |
| `tests/Unit/EnumsTest.php` | environment URLs, status and code classification |
| `tests/Unit/PaymentRequestTest.php` | payment body shape and validation |
| `tests/Unit/MarketplaceRequestTest.php` | `mpTerminalInfo`, conditions, invoice lines, split totals |
| `tests/Unit/WebhookHandlerTest.php` | callback shapes by `event`, both signing formulas, PascalCase tokenization, replay |
| `tests/Feature/PaymentClientTest.php` | payments over `FakeTransport` |
| `tests/Feature/TokenizationClientTest.php` | bind, charge, status, remove |
| `tests/Feature/MarketplaceClientTest.php` | split, confirm, VSA/MCR `extra`, cancel, Salom conditions (GET), currency |
| `tests/Feature/SubscriptionClientTest.php` | create (202), schedule rules, status, cancel errors |
| `tests/Feature/ProviderServerTest.php` | Basic auth, request parsing, response codes |

## FakeTransport

`tests/Support/FakeTransport.php` implements `Transport` (POST and GET), records `lastMethod`,
`lastUrl`, `lastBody`, `lastHeaders` and `calls`, and returns a queued response. It is shipped in the package rather than
hidden in the test suite so applications can use it too.

## What a test for a new endpoint must assert

1. The URL it posts to.
2. The exact signing string, by rebuilding it with `Signature` rather than pasting a hex digest.
3. Where the signature travels — body `token`, body `hash`, `hash` header or `Token` header.
4. The body shape, field by field, including whether an amount is a string or a number.
5. That a request the gateway would reject is refused locally, with `$transport->calls === 0`.
