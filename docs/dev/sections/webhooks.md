# Webhooks

`Webhook\WebhookHandler`, built through `AlifPay::webhooks()`. Alif posts to the callback URL and
counts it delivered only on exactly HTTP 200. Otherwise (or after a 20-second timeout) it retries:
five times about ten seconds apart, then hourly, up to fifteen attempts. The same callback can
arrive twice, so handlers must be idempotent (by `orderId` / `transaction_id` + `status`).

## Shapes

| Shape | Class | `event` | Signed over |
|-------|-------|---------|-------------|
| Payment: standard, marketplace, subscription charge | `PaymentCallback` | `payment` | `orderId + status + transactionId` |
| Delivery confirmed, or cancelled (standard or marketplace) | `DeliveryCallback` | `confirm` / `cancel` | `terminal_id + transaction_id + status` |
| Tokenization result | `TokenizationCallback` | `tokenize` (inside `Payload`) | not signed |

`WebhookHandler::callback()` picks between the first two with `DeliveryCallback::matches()`: an
`event` of `confirm` / `cancel`, or — for payloads without `event` — a `parent_transaction_id` or a
`terminal_id` with no `orderId`.

**Fixed in 3.0.0.** Every payment callback now carries `"event": "payment"`, and 2.x treated any
`event` as a delivery event, so standard payment callbacks came out as empty `DeliveryCallback`s.
The standard cancellation callback has no parent fields; `isSubTransaction()` tells the two
`DeliveryCallback` kinds apart.

## Verification

`verifyCallback()` picks the formula by shape. Underneath:

- `verifyPaymentCallback()` checks `HMAC(orderId + status + transactionId)`. Because the status is
  signed, a failed payment replayed as a successful one does not verify.
- `verifyDeliveryCallback()` checks `HMAC(terminal_id + transaction_id + status)`. 2.x said these
  callbacks carried no token; the docs (payments → "Уведомление об отмене", marketplace →
  "Формулы различаются") say they do.

`verifyStatusResponse()` does the same for a `/checktxn` answer;
`assertValidStatusResponse()` throws instead of returning false.

A custom signing string can be passed if a terminal is set up differently.

**What verification does not give you.** A verified callback is not a settled payment. The URL
is public. Confirm with a status lookup before handing goods over.

## Tokenization callback keys

Alif sends `Code`, `Message`, `Messages`, `ReasonCode`, `Payload` in PascalCase, and
`Payload.transaction_type` without the `tokenization_` prefix. 2.x read lowercase keys only and
parsed real callbacks as code 0 with an empty payload. `TokenizationCallback::fromArray()` reads
PascalCase first and lowercase second; `method()` strips the prefix either way.

## Responding

Answer HTTP 200, or Alif keeps retrying. In Laravel the callback route must be excluded from CSRF
protection, being a server-to-server POST.
