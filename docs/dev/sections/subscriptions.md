# Subscriptions

`Clients\SubscriptionClient`, built through `AlifPay::subscriptions()`. Regular charges of a fixed
amount from a payment method bound earlier through tokenization. Added in 3.0.0 from
<https://docs.acquiring.alif.tj/subscription>.

## Prerequisite

A tokenization in status `approved`. Its `Payload.transactionId` is the `token_request_id`, and the
`clientID` used to bind it is the `partner_client_id`. Supported: Korti Milli, Alif mobi, Tcell,
MegaFon, Babilon-Mobile, ZET-MOBILE. Not Salom, not international cards, not marketplace terminals.

## Shape of these endpoints

Unlike the rest of the API they name the terminal `terminal_id` (not `key`) and carry the signature
in a body field called `hash`. Every answer is `{ code, message, payload }`; `cancel` has no
`payload` on success.

| Method | Endpoint | Signing string | OK code |
|--------|----------|----------------|---------|
| `create()` | `POST /subscription/create` | `terminal_id + order_id + amount + token_request_id` (amount with 2 decimals) | 202 |
| `status()` | `POST /subscription/status` | `terminal_id + order_id` | 200 |
| `cancel()` | `POST /subscription/cancel` | `terminal_id + order_id` | 200 |

## SubscriptionRequest

- `order_id` 1–64 characters, `token_request_id` numeric, `partner_client_id` non-empty, amount
  positive — all checked in the constructor.
- Exactly one schedule: `period(SubscriptionPeriod)` (daily, weekly, monthly, yearly) or
  `every(count, IntervalUnit)` (day ≤ 90, week ≤ 52, month ≤ 24, year ≤ 3). Neither or both is
  refused when the body is built.
- `endDate()` takes a string or a `DateTimeInterface` (sent as RFC3339). Without it the
  subscription runs until cancelled.
- The amount is sent as a number, as in the docs' examples, and signed in its two-decimal form.

## Lifecycle

`accepted` → first charge → `approved` (runs on schedule) or `failed` (no retry, create again).
`canceled` by you, `completed` after `end_date`. `Enums\SubscriptionStatus` has `isActive()`,
`isFinal()`, `isCancelable()`. Failed scheduled charges are retried up to four times by Alif; the
first charge is not.

The schedule is anchored to the creation day, so a subscription created on 31 January charges on
28 February and then 31 March.

## Callbacks

Each charge is a normal payment callback (`event: payment`, `info: "Оплата по подписке"`, orderId
like `SUB_ORDER_123_45_0`), verified with `verifyPaymentCallback()`. Do not treat a subscription as
active from the create answer — wait for the first charge or call `status()`.

## Errors worth knowing

- A duplicate `order_id` on create answers **500**, not 208. Check `status()` before retrying.
- Cancelling a `failed` / `completed` / `canceled` subscription answers 400 with
  `payload.subscription_status` — available on `ApiException::$payload`.
