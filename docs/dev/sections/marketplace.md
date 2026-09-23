# Marketplace

`Clients\MarketplaceClient`, built through `AlifPay::marketplace()`. One payment, split between
sellers, held until delivery is confirmed.

## initiate()

`POST /v2/` with `isMarketPlace: true`. The splits go out as **`mpTerminalInfo`**.

That field name is the breaking change in 2.0.0. The package previously sent
`mp_terminal_amounts` with only `terminal_id` and `amount`; the current documentation names
`mpTerminalInfo` with `terminal_id`, `amount`, `condition_id` and `invoices`, and that is what
`Requests\TerminalSplit` builds.

- `condition_id` is the Salom installment condition, `0` for every other method.
- `invoices` describes what that seller is selling. `InvoiceItem::toSplitArray()` emits
  `name`, `category`, `quantity`, `price` with a numeric price.
- The splits must add up to the order amount. The check runs locally first, because the gateway's
  answer ("Сумма разбивки не соответствует сумме запроса") costs a round trip.

**Open question.** Alif's marketplace page contradicts itself on the invoice shape: its request
examples show `{name, category, quantity, price}`, while its parameter table lists
`{amount, label, store_id}`. This package follows the examples, since those are concrete request
bodies. Confirm against a real terminal before trusting itemized Salom splits.

The top-level `amount` is sent as a string, as the payments documentation specifies for `/v2/`;
the amounts inside `mpTerminalInfo` are sent as numbers, as the marketplace examples show.

## confirmDelivery()

`POST /confirm-delivery`, for everything except Visa/Mastercard. An amount below the held one
confirms partially: the transaction becomes `partially_approved` and the rest can be confirmed by
a further call.

## confirmVsaMcrDelivery()

`POST /confirm-vsa-and-mcr-delivery`, for Visa/Mastercard. Takes the parent transaction plus one
`Requests\DeliveryConfirmation` per child, which go out as the `extra` array. Only the parent is
signed.

Sending it without children is refused locally — Alif answers "Список транзакций для
подтверждения пуст", and the package used to send exactly that empty request.

## cancel()

`POST /cancel/marketplace`, and only against a **child** transaction: the parent cannot be
cancelled directly. A held (`to_approve`) transaction cancels in full only; a captured (`ok`) one
can cancel partially and becomes `partially_canceled`. Salom is not cancellable here. `reason` is
optional.

## checkStatus()

`POST /checktxn` with `orderId`, exactly as for a standard payment — no marketplace header, which
the documentation does not ask for. The answer adds `sub_transactions`.

## Statuses

`pending`, `to_approve` (held), `ok`, `partially_approved`, `failed`, `canceled`,
`partially_canceled` — all in `Enums\PaymentStatus`.
