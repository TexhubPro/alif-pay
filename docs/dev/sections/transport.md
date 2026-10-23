# Transport and errors

## The interface

`Http\Transport` has two methods: `post(string $url, array $body, array $headers): RawResponse`
and, since 3.0.0, `get(string $url, array $headers): RawResponse` for `GET /conditions/{key}`.
Adding `get()` is a breaking change for custom transports. Everything above it is pure logic,
which is why the whole SDK is testable without a network: `tests/Support/FakeTransport.php`
records the last method, URL, body and headers and returns a canned response.

`Http\CurlTransport` is the default. It sets certificate verification, the HTTPS-only protocol
allowlist and `FOLLOWLOCATION => false` explicitly: a body carrying a terminal token must not be
replayable to whatever host a redirect names. It encodes with `JSON_PRESERVE_ZERO_FRACTION` so an
amount of `100.00` stays a decimal rather than collapsing to `100`.

## Reading a response

`AbstractClient::send()` decodes the body and hands back `Http\Response` — `code`, `message`,
`url`, plus `raw` for everything else.

Most endpoints answer `{ code, message, url }` and the `code` mirrors the HTTP status: a duplicate
order is HTTP 208 with `code: 208`, a bad token is HTTP 401 with `code: 401`.

**Status lookups are the exception.** A transaction that was found comes back as itself —
`orderId`, `transactionId`, `status`, `token`, `amount` — with no `code` anywhere. Only a failure
carries one. `send()` takes `$expectsCode = false` for those, otherwise every successful lookup
would be raised as an error.

`send()` also takes `$okCodes` — subscription creation answers 202. Everything else must be 200;
a 203 from `/pay/token` (declined) is raised like any failure.

## Exceptions

| Exception | Raised when |
|-----------|-------------|
| `TransportException` | the request never completed: connection refused, timeout, unreadable body |
| `ApiException` | a response arrived and reports a failure; carries `httpStatus`, `apiCode`, `apiMessage`, `payload` |
| `InvalidSignatureException` | a callback, status response or provider request failed verification |
| `ConfigurationException` | the SDK was asked to send something the gateway would reject anyway |
| `AlifPayException` | the base class of all of the above |

`ApiException::isRetryable()` is true for 500 only — the docs mark nothing else as worth retrying
(2.x also retried 404, which means "not found" there). `isDuplicate()` is true for 208. 403 is not
in the current docs; `ResponseCode::Forbidden` stays so an older answer parses.
