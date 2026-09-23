# Webhooks

`Webhook\WebhookHandler`, built through `AlifPay::webhooks()`. Alif posts to the callback URL and
retries until it gets HTTP 200 — every 10 seconds for marketplace, every 5 minutes for an hour for
tokenization.

## Three shapes, one URL

| Shape | Class | Recognized by |
|-------|-------|---------------|
| Payment / marketplace payment | `PaymentCallback` | `orderId` + `token` |
| Delivery confirmed or cancelled | `DeliveryCallback` | `event` / `parent_transaction_id` |
| Tokenization result | `TokenizationCallback` | code at the root, data under `payload` |

`WebhookHandler::callback()` picks between the first two. Before 2.0.0 only the first existed, so
a delivery or cancellation event was parsed as a payment callback and came out empty — the event
was silently lost.

## Verification

`verifyPaymentCallback()` checks `HMAC(orderId + status + transactionId)` against the `token` in
the body. Because the status is signed, a failed payment replayed as a successful one does not
verify.

`verifyStatusResponse()` does the same for a `/checktxn` answer;
`assertValidStatusResponse()` throws instead of returning false.

A custom signing string can be passed if a terminal is set up differently.

**Two things verification does not give you.**

1. A verified callback is not a settled payment. The URL is public. Confirm with a status lookup
   before handing goods over.
2. A `DeliveryCallback` carries no token at all, so it cannot be verified. Treat it as a hint that
   something moved and confirm with `checkStatus()`.

## Responding

Answer HTTP 200, or Alif keeps retrying. In Laravel the callback route must be excluded from CSRF
protection, being a server-to-server POST.
