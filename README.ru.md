# TexHub · Alif Pay

[English](README.md) · **Русский**

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012%20%7C%2013-ff2d20.svg)](#laravel)

Чистый, не привязанный к фреймворку PHP SDK для платёжного шлюза **Alif Acquiring (WebCheckout)** — платежи, токенизация, подписки, маркетплейс-платежи (split-payment) — и протокол провайдера, где Alif обращается к вашему серверу. С полной поддержкой **Laravel**.

> Работает в чистом PHP и любом фреймворке. Для Laravel — авто-дискавери, конфиг и фасад из коробки.

Основано на официальной документации: <https://docs.acquiring.alif.tj> и <https://alifcapital.github.io>

---

## Возможности

- **Стандартные платежи** — Korti Milli, кошелёк Alif, рассрочка Salom, оплата наличными по инвойсу, Visa/Mastercard
- **Токенизация** — привязать карту, кошелёк или номер оператора, затем списывать, проверять статус и отвязывать
- **Подписки** — регулярные списания с привязанного средства по готовому или своему расписанию
- **Маркетплейс** — разделение одного платежа между несколькими продавцами, холдирование и подтверждение доставки, условия Salom
- **Валюта** — валюта терминала и её курс к сомони
- **Эндпоинт провайдера** — обратное направление: обработка запросов `check` / `pay` / `status`, которые шлёт Alif
- **Подпись HMAC SHA256** — точная схема двойного HMAC, которую требует Alif, делается за вас
- **Вебхуки** — типизированные колбэки оплаты, подтверждения, отмены и токенизации, каждый проверяется своей формулой
- **Подменяемый HTTP-транспорт** — cURL по умолчанию; можно внедрить свой для тестов
- **Полностью покрыт тестами**, без обращения к сети
- Переключение **Test / Production**

---

## Установка

```bash
composer require texhub/alif-pay
```

Требования: **PHP ≥ 8.2** с расширениями `curl`, `json` и `hash`.

---

## Быстрый старт (чистый PHP)

```php
use TexHub\AlifPay\AlifPay;
use TexHub\AlifPay\Enums\Environment;
use TexHub\AlifPay\Enums\Gate;
use TexHub\AlifPay\Requests\PaymentRequest;

$alif = AlifPay::make(
    terminalId: 'YOUR_TERMINAL_ID',
    terminalPassword: 'YOUR_TERMINAL_PASSWORD',
    environment: Environment::Test, // Environment::Production в бою
);

$response = $alif->payments()->initiate(
    PaymentRequest::make('ORDER_123456', '100.50')
        ->gate(Gate::KortiMilli)
        ->callbackUrl('https://shop.tj/alif/callback')
        ->returnUrl('https://shop.tj/success')
        ->info('Оплата заказа №123456')
        ->phone('992900123456')
);

// Перенаправьте покупателя на защищённую платёжную форму:
header('Location: ' . $response->redirectUrl());
```

---

## Окружения

| Окружение | Базовый URL |
|---------------------------|----------------------------|
| `Environment::Test` | `https://test-web.alif.tj` |
| `Environment::Production` | `https://web.alif.tj` |

В тестовой среде есть тестовые карты для проверки сценариев без движения реальных денег; Alif выдаёт их вместе с тестовым терминалом (в документации их нет).

---

## Авторизация (как работает подпись)

Каждый запрос авторизуется токеном HMAC SHA256 на основе данных запроса:

```
token = HMAC_SHA256( dataToSign, HMAC_SHA256(terminal_password, terminal_id) )
```

SDK сам формирует правильный `dataToSign` для каждой операции:

| Операция | `dataToSign` | Передаётся в |
|--------------------------|--------------------------------------------------|-------------------|
| Платёж / Маркетплейс | `terminal_id + order_id + amount + callback_url` | теле, `token` |
| `info` с налоговыми реквизитами | `info` | теле, `info_hash` |
| Привязка токена | `terminal_id + phone + gate` | теле, `token` |
| Проверка статуса | `terminal_id + orderId` | теле, `token` |
| Отмена | `terminal_id + transaction_id + amount` | теле, `token` |
| Подтверждение доставки | `terminal_id + transaction_id + amount` | теле, `token` |
| Подтверждение VSA/MCR | `terminal_id + parent_transaction_id` | теле, `token` |
| Списание по токену | `terminal_id + order_id + amount + token` | заголовке `hash` |
| Статус токенизации | `terminal_id + request_id` | заголовке `hash` |
| Удаление токена | `terminal_id + token` | заголовке `hash` |
| Валюта и курс | `terminal_id + order_id` | теле, `token` |
| Условия Salom | `terminal_id` | заголовке `Token` |
| Создание подписки | `terminal_id + order_id + amount + token_request_id` | теле, `hash` |
| Статус / отмена подписки | `terminal_id + order_id` | теле, `hash` |

Списание по токену и запросы по токенизации передают подпись в заголовке `hash`, а `token` в их теле — это UUID привязанного платёжного средства. Подписки называют терминал `terminal_id` и кладут подпись в поле тела `hash`.

Вручную вызывать подпись не нужно, но при необходимости она доступна через `$alif->signature()`.

---

## Платежи

### Шлюзы (`Gate`)

| Enum | Значение `gate` | Метод |
|-----------------------------|------------------------|-----------------------------|
| `Gate::KortiMilli` | `korti_milli` | Нац. карта *(по умолчанию)* |
| `Gate::Wallet` | `wallet` | Кошелёк Alif mobi |
| `Gate::Salom` | `salom` | Рассрочка Salom |
| `Gate::Invoice` | `invoice` | Оплата наличными |
| `Gate::Visa` | `visa` | Visa, на форме Алифа |
| `Gate::Mastercard` | `mastercard` | Mastercard, на форме Алифа |
| `Gate::CybersourceCheckout` | `cybersource_checkout` | Международные карты на форме Cybersource |

В старых примерах Alif Visa и Mastercard назывались `vsa` и `mcr`. Сейчас в документации `visa` и `mastercard`, и enum с версии 3.0 тоже. Если терминал настроен со старыми именами, передайте их строкой:

```php
PaymentRequest::make('ORDER_1', '100.00')->gateValue('vsa');
```

`gateValue()` принимает любой шлюз в нижнем регистре. Что шлюз делает с незнакомым значением, в документации не сказано, так что используйте его, только если Alif сам его дал. Google Pay, Apple Pay и операторы связи упомянуты в документации как поддерживаемые способы, но значения `gate` для оплаты у них не опубликованы.

### Налоговые реквизиты в `info`

Если в `info` передаются налоговые реквизиты, документация просит подпись этого поля в `info_hash`. Только для Korti Milli:

```php
PaymentRequest::make('ORDER_1', '100.00')
    ->gate(Gate::KortiMilli)
    ->info('ИНН 123456789, НДС 0%')
    ->signInfo();
```

### Рассрочка Salom (с позициями инвойса)

```php
use TexHub\AlifPay\Requests\InvoiceItem;

$response = $alif->payments()->initiate(
    PaymentRequest::make('ORDER_345678', '1500.00')
        ->gate(Gate::Salom)
        ->callbackUrl('https://shop.tj/alif/callback')
        ->returnUrl('https://shop.tj/success')
        ->phone('992900111222')
        ->addInvoiceItem(new InvoiceItem(
            name: 'Смартфон Samsung Galaxy A54',
            category: 'Электроника',
            quantity: 1,
            price: '1500.00',
            vatPercent: '0',
        ))
);
```

### Оплата наличными (с дедлайном)

```php
$alif->payments()->initiate(
    PaymentRequest::make('ORDER_678900', '1200.00')
        ->gate(Gate::Invoice)
        ->callbackUrl('https://shop.tj/alif/callback')
        ->returnUrl('https://shop.tj/success')
        ->deadline('2025-11-29T07:59:59Z')
);
```

### Проверка статуса / отмена

```php
$status = $alif->payments()->checkStatus('ORDER_123456');

$status->get('status');         // "ok"
$status->get('transactionId');  // "789012"

$alif->payments()->cancel(transactionId: '789012', amount: '100.50', reason: 'Возврат по заявке клиента');
```

Найденная транзакция возвращается сама собой — `orderId`, `transactionId`, `status`, `token`, `amount` — без поля `code`; оно есть только у ошибок. Прежде чем действовать по статусу, проверьте её `token` (см. [Проверка того, что присылает Alif](#проверка-того-что-присылает-alif)).

Отмена только полная, только для платежей в статусе `ok` по Korti Milli, Alif Wallet и Salom, а Salom — лишь в течение 14 дней. Ответ 200 значит, что заявка принята; результат придёт колбэком `cancel`, и статус платежа станет `canceled`.

### Валюта и курс

Если терминал не в сомони, на форме плательщик всё равно видит сомони. Курс можно узнать заранее:

```php
$rates = $alif->payments()->currency('ORDER_123456');

$rates->currency;          // "USD" — валюта терминала
$rates->rate;              // 10.95 — к сомони
$rates->rateFor('EUR');    // 11.80
$rates->toSomoni('100.50'); // "1100.48"
```

Курс обновляется каждые 10 минут; у терминала в сомони он всегда 1.

---

## Токенизация

```php
use TexHub\AlifPay\Enums\TokenizationGate;
use TexHub\AlifPay\Requests\TokenizationRequest;

$response = $alif->tokenization()->initiate(
    TokenizationRequest::make('ORDER_123456', '+992900123456', TokenizationGate::Wallet)
        ->callbackUrl('https://shop.tj/alif/tokenize-callback')
        ->returnUrl('https://shop.tj/success')
        ->clientId('client_12345')
);

header('Location: ' . $response->redirectUrl());
```

Доступные шлюзы: `KortiMilli`, `Wallet`, `Salom`, `Tcell`, `Megafon`, `Babilon`, `ZetMobile`, `Visa`, `Mastercard`. Visa и Mastercard подтверждаются 3-D Secure в банке карты, остальные — одноразовым кодом. `Procard` оставлен для старых терминалов и помечен устаревшим.

`clientId()` обязателен — без него шлюз отклоняет привязку, а подписка потом должна указать то же значение.

Номер должен быть таджикским. Пишите как удобно — `+992900123456`, `992900123456`, `900 123-456` — SDK приведёт его к документированному виду до того, как подпишет.

### Списание по привязанному токену

Привязка — это только половина дела. Когда колбэк токенизации отдал токен, списывайте:

```php
use TexHub\AlifPay\Requests\TokenChargeRequest;

$alif->tokenization()->charge(
    TokenChargeRequest::make('ORDER_123456', '100.00', $savedToken)
        ->callbackUrl('https://shop.tj/alif/callback')
        ->email('customer@example.com')
        ->info('Подписка на месяц')
);
```

Добавьте разбивку — и это станет маркетплейс-списанием:

```php
TokenChargeRequest::make('ORDER_1', '100.00', $savedToken)
    ->callbackUrl('https://shop.tj/alif/callback')
    ->splitTo('TERM_001', '70.00')
    ->splitTo('TERM_002', '30.00');
```

### Статус и удаление

```php
$state = $alif->tokenization()->status('ORDER_123456');
$state->get('payload')['status'];   // accepted | approved | duplicate | failed | removed

$alif->tokenization()->remove('ORDER_123456', $savedToken);
```

`TokenizationState` типизирует эти статусы, а `$callback->state()?->isUsable()` отвечает на главный вопрос: можно ли списывать по этому токену.

Отклонённое списание по токену приходит с HTTP **203**. SDK бросает на него `ApiException`, как на любую ошибку.

---

## Подписки

Фиксированная сумма, которая списывается по расписанию с ранее привязанного средства. Поддерживаются Korti Milli, Alif mobi и операторы связи; Salom и международные карты — нет.

```php
use TexHub\AlifPay\Enums\IntervalUnit;
use TexHub\AlifPay\Enums\SubscriptionPeriod;
use TexHub\AlifPay\Requests\SubscriptionRequest;

$created = $alif->subscriptions()->create(
    SubscriptionRequest::make(
        orderId: 'SUB_ORDER_123',
        tokenRequestId: '45781',        // Payload.transactionId из колбэка успешной токенизации
        partnerClientId: 'client_12345', // clientID, указанный при той привязке
        amount: '99.90',
    )
        ->period(SubscriptionPeriod::Monthly)       // или ->every(3, IntervalUnit::Month)
        ->endDate('2027-01-01T00:00:00Z')           // необязательно
        ->callbackUrl('https://shop.tj/alif/callback')
);

$created->subscriptionId;   // 1024
$created->status;           // SubscriptionStatus::Accepted

$details = $alif->subscriptions()->status('SUB_ORDER_123');
$details->status?->isActive();

$alif->subscriptions()->cancel('SUB_ORDER_123');
```

Расписание задаётся ровно одним способом — готовым периодом или своим интервалом (дни до 90, недели до 52, месяцы до 24, годы до 3). Новая подписка находится в `accepted` и становится `approved` только после успешного первого списания; если оно не прошло, подписка сразу `failed`, без повторов. Каждое списание приходит обычным колбэком оплаты (`event: payment`, `info: "Оплата по подписке"`). Отмена останавливает только будущие списания; прошлые возвращаются через `payments()->cancel()`.

---

## Маркетплейс (split-payment)

```php
use TexHub\AlifPay\Requests\MarketplaceRequest;

$response = $alif->marketplace()->initiate(
    MarketplaceRequest::make('MP_ORDER_123456', '500.00')
        ->gate(Gate::KortiMilli)
        ->callbackUrl('https://shop.tj/alif/mp-callback')
        ->returnUrl('https://shop.tj/success')
        ->splitTo('partner_terminal_1', '300.00')
        ->splitTo('partner_terminal_2', '200.00')
);
```

> Сумма разбивки **обязана** равняться сумме заказа — SDK проверяет это перед отправкой.

Доля продавца может нести свои позиции накладной и условие рассрочки Salom:

```php
use TexHub\AlifPay\Requests\InvoiceItem;
use TexHub\AlifPay\Requests\TerminalSplit;

$alif->marketplace()->initiate(
    MarketplaceRequest::make('MP_SALOM_1', '1200.00')
        ->gate(Gate::Salom)
        ->callbackUrl('https://shop.tj/alif/mp-callback')
        ->returnUrl('https://shop.tj/success')
        ->addSplit(
            TerminalSplit::make('SELLER_005', '1200.00', conditionId: 12)
                ->addInvoiceItem(new InvoiceItem(
                    name: 'Смартфон Samsung Galaxy A54',
                    category: 'smartphones',
                    quantity: 1,
                    price: '1200.00',
                ))
        )
);
```

Средства холдируются до подтверждения доставки:

```php
// Все методы, кроме Visa/Mastercard — меньшая сумма подтверждает частично:
$alif->marketplace()->confirmDelivery(transactionId: '789013', amount: '300.00');

// Visa / Mastercard — все дочерние транзакции одним запросом:
use TexHub\AlifPay\Requests\DeliveryConfirmation;

$alif->marketplace()->confirmVsaMcrDelivery(
    '789012',
    new DeliveryConfirmation('789013', '150.00'),
    new DeliveryConfirmation('789014', '350.00'),
);

// Статус и отмена (только дочерняя транзакция, не родительская):
$alif->marketplace()->checkStatus('MP_ORDER_123456');
$alif->marketplace()->cancel(transactionId: '789013', amount: '300.00', reason: 'Отмена заказа покупателем');
```

Условия Salom по каждому терминалу продавца — чтобы выбрать `condition_id` до платежа:

```php
foreach ($alif->marketplace()->salomConditions() as $terminal) {
    $terminal->terminalId;                 // "200001"
    foreach ($terminal->conditions as $condition) {
        $condition->id;                    // 12 — передаётся в splitTo(..., conditionId: 12)
        $condition->displayName;           // "Рассрочка на 6 месяцев"
    }
}

$alif->marketplace()->currency('MP_ORDER_123');  // то же, что payments()->currency()
```

---

## Вебхуки (колбэки)

Alif отправляет `POST` на ваш `callback_url` при каждом изменении статуса. Отвечайте строго **HTTP 200**: любой другой ответ (или его отсутствие 20 секунд) повторяется — пять раз примерно через 10 секунд, потом раз в час, до 15 попыток. Один и тот же колбэк может прийти несколько раз, обработчик должен быть идемпотентным.

### Колбэк оплаты

Стандартные платежи, маркетплейс и списания по подписке приходят колбэком оплаты с `"event": "payment"`.

```php
use TexHub\AlifPay\Enums\PaymentStatus;

$callback = $alif->webhooks()->paymentCallback(file_get_contents('php://input'));

// Проверьте подлинность перед доверием (см. примечание ниже):
if (! $alif->webhooks()->verifyPaymentCallback($callback)) {
    http_response_code(400);
    exit;
}

match ($callback->status) {
    PaymentStatus::Ok       => markOrderPaid($callback->orderId, $callback->amount),
    PaymentStatus::Failed,
    PaymentStatus::Canceled => markOrderFailed($callback->orderId),
    default                 => null, // pending / to_approve
};

http_response_code(200);
echo 'OK';
```

Для маркетплейса в `$callback->subTransactions` лежит разбивка по партнёрам, а `$callback->isMarketplace()` равно `true`. Доступны также `$callback->event` и `$callback->info`.

### События подтверждения и отмены

Подтверждение доставки (`event: confirm`) и отмена (`event: cancel`) приходят на тот же URL другим форматом — одна транзакция, без `orderId`. Подписаны они **другой** формулой: `terminal_id . transaction_id . status`. Пусть обработчик сам выберет и формат, и формулу:

```php
use TexHub\AlifPay\Webhook\DeliveryCallback;
use TexHub\AlifPay\Webhook\PaymentCallback;

$callback = $alif->webhooks()->callback(file_get_contents('php://input'));

if (! $alif->webhooks()->verifyCallback($callback)) {
    http_response_code(403);
    exit;
}

match (true) {
    $callback instanceof PaymentCallback  => handlePayment($callback),
    $callback instanceof DeliveryCallback => handleDelivery($callback),
};
```

В `DeliveryCallback` есть `terminalId`, `transactionId`, `status`, `event`, `token`, а для маркетплейса ещё `parentTransactionId` и `parentStatus`. У отмены стандартного платежа родительских полей нет (`isSubTransaction()` равно `false`).

### Колбэк токенизации

> Структура колбэка токенизации **другая**: в корне `Code`, `Message` и `Payload`, в PascalCase. `transaction_type` приходит без префикса `tokenization_` (`$callback->method()` вернёт его в любом случае).

```php
$callback = $alif->webhooks()->tokenizationCallback(file_get_contents('php://input'));

if ($callback->isSuccessful()) {
    saveToken($callback->orderId, $callback->token); // сохраните для повторных платежей
}

http_response_code(200);
```

### Проверка того, что присылает Alif

Alif подписывает свои ответы той же HMAC-схемой. Колбэки оплаты и ответ проверки статуса — строкой `orderId . status . transactionId`; колбэки подтверждения и отмены — строкой `terminal_id . transaction_id . status`:

```php
$alif->webhooks()->verifyCallback($callback);                    // формула по event
$alif->webhooks()->verifyPaymentCallback($callback);             // колбэки оплаты
$alif->webhooks()->verifyDeliveryCallback($callback);            // колбэки confirm / cancel
$alif->webhooks()->verifyStatusResponse($alif->payments()->checkStatus($orderId)); // проверка статуса
```

Статус входит в подписанную строку, поэтому неуспешный платёж, подсунутый как успешный, подпись не пройдёт.

Если терминал настроен иначе — передайте свою строку:

```php
$alif->webhooks()->verifyPaymentCallback($callback, dataToSign: $yourString);
// или низкоуровневая проверка:
$alif->webhooks()->verifyToken($yourString, $callback->token);
```

**Проверенный колбэк — ещё не оплата.** Адрес колбэка публичный, поэтому перед выдачей товара подтвердите результат сами:

```php
$status = $alif->payments()->checkStatus($callback->orderId);

if ($alif->webhooks()->verifyStatusResponse($status) && $status->get('status') === 'ok') {
    // вот теперь оплачено
}
```

Найденная транзакция приходит сама собой — `orderId`, `transactionId`, `status`, `token`, `amount` — без поля `code`; код есть только у ошибок.

---

## Обработка ошибок

Шлюз отвечает HTTP-статусом, повторяющим бизнес-код `code`: дубликат заказа приходит как HTTP 208 с `code: 208`. SDK превращает любую неудачу в `ApiException`:

```php
use TexHub\AlifPay\Exceptions\ApiException;
use TexHub\AlifPay\Exceptions\TransportException;

try {
    $response = $alif->payments()->initiate($request);
} catch (ApiException $e) {
    $e->apiCode;        // 203, 208, 400, 401, 404, 500
    $e->apiMessage;     // человекочитаемое сообщение (RU)
    $e->isDuplicate();  // true для кода 208
    $e->isRetryable();  // true только для 500
} catch (TransportException $e) {
    // сетевая ошибка
}
```

| Код | Значение | Повтор |
|------|--------------------|--------|
| 200 | Успешно | — |
| 202 | Принято в обработку (подписка создана) | — |
| 203 | Шлюз отклонил списание по токену — это ошибка | Нет |
| 208 | Дубликат order_id | Нет |
| 400 | Ошибка валидации | Нет |
| 401 | Неверная подпись или терминал не найден | Нет |
| 404 | Не найдено | Нет |
| 500 | Внутренняя ошибка | Да |

403 в текущей документации нет; он всё ещё разбирается как `ResponseCode::Forbidden` на случай ответа старого шлюза.

Исключение — проверка статуса: у найденной транзакции поля `code` нет вовсе.

---

## Протокол провайдера

Обратное направление, описанное в <https://alifcapital.github.io/providers>: Alif обращается к **вашему** эндпоинту, чтобы его клиенты могли оплачивать вашу услугу в кассах и в приложении Alif. С эквайрингом у него нет ничего общего — ни HMAC, отдельные логин и пароль, свои коды результата.

```dotenv
ALIF_PAY_PROVIDER_LOGIN=your_login
ALIF_PAY_PROVIDER_PASSWORD=your_password
```

```php
use TexHub\AlifPay\Enums\ProviderAction;
use TexHub\AlifPay\Enums\ProviderCode;
use TexHub\AlifPay\Provider\ProviderResponse;

$provider = $alif->provider();

if (! $provider->authorize($_SERVER['HTTP_AUTHORIZATION'] ?? null)) {
    $provider->send(ProviderResponse::code(ProviderCode::Unauthorized));
    exit;
}

$request = $provider->request(file_get_contents('php://input'));

$response = match ($request->action) {
    ProviderAction::Check  => findSubscriber($request->account)
        ? ProviderResponse::accountFound($request->id, infoForClient: 'Баланс: 50.30 смн')
        : ProviderResponse::code(ProviderCode::AccountNotFound, $request->id),

    ProviderAction::Pay    => ProviderResponse::paid($request->id, credit($request->account, $request->amount)),

    ProviderAction::Status => ProviderResponse::status($request->id, ProviderCode::Success, $storedOperationId),

    default                => ProviderResponse::code(ProviderCode::BadRequest, $request->id),
};

$provider->send($response);
```

`$request->id` — идентификатор платежа в системе Alif и ваш ключ идемпотентности: на повторный `pay` с уже зачисленным id нужно отвечать `ProviderResponse::duplicate()`, а не зачислять второй раз.

Следите за семантикой повторов — `ProviderCode::isFatal()` показывает, какие коды завершают платёж, а какие Alif будет повторять до 24 часов:

```php
ProviderCode::InsufficientFunds->isFatal();  // false — Alif повторит
ProviderCode::AccountNotFound->isFatal();    // true  — Alif прекратит
```

---

## <a name="laravel"></a> Laravel

Сервис-провайдер и фасад `AlifPay` **регистрируются автоматически**. Опубликуйте конфиг:

```bash
php artisan vendor:publish --tag=alif-pay-config
```

Добавьте учётные данные в `.env`:

```dotenv
ALIF_PAY_ENVIRONMENT=test
ALIF_PAY_TERMINAL_ID=your_terminal_id
ALIF_PAY_TERMINAL_PASSWORD=your_terminal_password
ALIF_PAY_CALLBACK_URL=https://shop.tj/alif/callback
ALIF_PAY_RETURN_URL=https://shop.tj/success
ALIF_PAY_TIMEOUT=30

# Только для протокола провайдера:
ALIF_PAY_PROVIDER_LOGIN=
ALIF_PAY_PROVIDER_PASSWORD=
```

Используйте фасад (callback/return URL берутся из конфига):

```php
use TexHub\AlifPay\Laravel\AlifPay;
use TexHub\AlifPay\Enums\Gate;
use TexHub\AlifPay\Requests\PaymentRequest;

$response = AlifPay::payments()->initiate(
    PaymentRequest::make('ORDER_'.$order->id, $order->total)->gate(Gate::KortiMilli)
);

return redirect()->away($response->redirectUrl());
```

…либо внедрите через DI:

```php
public function pay(\TexHub\AlifPay\AlifPay $alif) { /* ... */ }
```

### Пример контроллера колбэка

```php
use Illuminate\Http\Request;
use TexHub\AlifPay\Laravel\AlifPay;
use TexHub\AlifPay\Enums\PaymentStatus;
use TexHub\AlifPay\Webhook\PaymentCallback;

public function callback(Request $request)
{
    $callback = AlifPay::webhooks()->callback($request->getContent());

    if (! AlifPay::webhooks()->verifyCallback($callback)) {
        return response('', 403);
    }

    if ($callback instanceof PaymentCallback && $callback->status === PaymentStatus::Ok) {
        $status = AlifPay::payments()->checkStatus($callback->orderId);

        if (AlifPay::webhooks()->verifyStatusResponse($status) && $status->get('status') === 'ok') {
            Order::where('reference', $callback->orderId)->update(['status' => 'paid']);
        }
    }

    return response('OK', 200);
}
```

> Исключите маршрут колбэка из CSRF-защиты (`VerifyCsrfToken::$except`), так как это server-to-server `POST`.

---

## Тестирование

В комплекте есть фейковый транспорт — можно тестировать без обращения к сети:

```php
use TexHub\AlifPay\AlifPay;
use TexHub\AlifPay\Config;
use TexHub\AlifPay\Tests\Support\FakeTransport;

$transport = (new FakeTransport())->willReturnJson([
    'code' => 200, 'message' => 'Успешно', 'url' => 'https://web.alif.tj/abc',
]);

$alif = new AlifPay(new Config('id', 'secret'), $transport);
// ... проверяйте $transport->lastBody / lastHeaders / lastUrl / lastMethod
```

Запуск тестов пакета:

```bash
composer install
composer test          # или: vendor/bin/phpunit
```

---

## Архитектура

```
src/
├── AlifPay.php              # точка входа — payments()/tokenization()/subscriptions()/marketplace()/webhooks()/provider()
├── Config.php               # неизменяемая конфигурация
├── Signature.php            # подпись HMAC SHA256 (двойной хэш)
├── Enums/                   # Environment, Gate, PaymentStatus, TokenizationState, ProviderCode, …
├── Http/                    # интерфейс Transport (POST и GET), CurlTransport, Response
├── Requests/                # PaymentRequest, TokenChargeRequest, SubscriptionRequest, MarketplaceRequest, …
├── Responses/               # CurrencyRates, TerminalConditions, SubscriptionCreated, SubscriptionDetails
├── Clients/                 # PaymentClient, TokenizationClient, SubscriptionClient, MarketplaceClient
├── Webhook/                 # DTO колбэков + WebhookHandler
├── Provider/                # эндпоинт, который вызывает Alif: check / pay / status
├── Exceptions/              # ApiException, TransportException, …
└── Laravel/                 # ServiceProvider + Facade
```

Документация для разработчика — архитектура, все строки подписи, заметки по каждой области и открытые вопросы в самой документации Alif — лежит в [`docs/dev/`](docs/dev/README.md).

---

## История изменений

См. [CHANGELOG.md](CHANGELOG.md). Версия 3.0.0 переименовывает значения шлюзов Visa и Mastercard в `visa` / `mastercard`, добавляет `get()` в интерфейс `Transport` и делает `clientId()` обязательным для токенизации; 2.0.0 сменила поле разбивки маркетплейса. Прочитайте перед обновлением.

---

## Лицензия

MIT © TexHub Pro — разработано Mahmudi Shodmehr.
