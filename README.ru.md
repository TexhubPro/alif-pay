# TexHub · Alif Pay

[English](README.md) · **Русский**

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012%20%7C%2013-ff2d20.svg)](#laravel)

Чистый, не привязанный к фреймворку PHP SDK для платёжного шлюза **Alif Acquiring (WebCheckout)** — платежи, токенизация, маркетплейс-платежи (split-payment) — и протокол провайдера, где Alif обращается к вашему серверу. С полной поддержкой **Laravel**.

> Работает в чистом PHP и любом фреймворке. Для Laravel — авто-дискавери, конфиг и фасад из коробки.

Основано на официальной документации: <https://docs.acquiring.alif.tj> и <https://alifcapital.github.io>

---

## Возможности

- **Стандартные платежи** — Korti Milli, кошелёк Alif, рассрочка Salom, оплата наличными по инвойсу, Visa/Mastercard
- **Токенизация** — привязать карту или кошелёк, затем списывать, проверять статус и отвязывать
- **Маркетплейс** — разделение одного платежа между несколькими продавцами, холдирование и подтверждение доставки
- **Эндпоинт провайдера** — обратное направление: обработка запросов `check` / `pay` / `status`, которые шлёт Alif
- **Подпись HMAC SHA256** — точная схема двойного HMAC, которую требует Alif, делается за вас
- **Вебхуки** — типизированные объекты для всех трёх форматов колбэков + проверка подписи
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

В тестовой среде можно использовать тестовые карты Alif для разных сценариев (заблокированная карта, недостаточно средств и т.д.) без движения реальных денег.

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
| Привязка токена | `terminal_id + phone + gate` | теле, `token` |
| Проверка статуса | `terminal_id + orderId` | теле, `token` |
| Отмена | `terminal_id + transaction_id + amount` | теле, `token` |
| Подтверждение доставки | `terminal_id + transaction_id + amount` | теле, `token` |
| Подтверждение VSA/MCR | `terminal_id + parent_transaction_id` | теле, `token` |
| Списание по токену | `terminal_id + order_id + amount + token` | заголовке `hash` |
| Статус токенизации | `terminal_id + request_id` | заголовке `hash` |
| Удаление токена | `terminal_id + token` | заголовке `hash` |

Три самых новых эндпоинта передают подпись в заголовке `hash`, а `token` в их теле — это UUID привязанного платёжного средства.

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
| `Gate::Visa` | `vsa` | Visa |
| `Gate::Mastercard` | `mcr` | Mastercard |
| `Gate::CybersourceCheckout` | `cybersource_checkout` | Cybersource hosted checkout |

Alif поддерживает больше методов, чем публикует значений `gate`. Если нужного нет в таблице, передайте его строкой:

```php
PaymentRequest::make('ORDER_1', '100.00')->gateValue('google_pay');
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

Для стандартных платежей возможна только полная отмена, а Salom отменяется лишь в течение 14 дней.

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

Доступные шлюзы: `KortiMilli`, `Wallet`, `Salom`, `Tcell`, `Megafon`, `Babilon`, `ZetMobile`, `Procard` (Visa/Mastercard).

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

---

## Вебхуки (колбэки)

Alif отправляет `POST` на ваш `callback_url` при каждом изменении статуса. Отвечайте **HTTP 200**, иначе будет повтор.

### Колбэк платежа / маркетплейса

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

Для маркетплейса в `$callback->subTransactions` лежит разбивка по партнёрам, а `$callback->isMarketplace()` равно `true`.

### События доставки и отмены

Маркетплейс шлёт на тот же URL ещё два формата — по одному на подтранзакцию, после подтверждения доставки или отмены. Пусть обработчик сам их различит:

```php
use TexHub\AlifPay\Webhook\DeliveryCallback;
use TexHub\AlifPay\Webhook\PaymentCallback;

$callback = $alif->webhooks()->callback(file_get_contents('php://input'));

match (true) {
    $callback instanceof PaymentCallback  => handlePayment($callback),
    $callback instanceof DeliveryCallback => handleDelivery($callback),
};
```

В `DeliveryCallback` есть `terminalId`, `transactionId`, `status`, `event`, `parentTransactionId` и `parentStatus` — но **нет токена**, поэтому проверить подпись нечем. Считайте это сигналом, что что-то изменилось, и подтверждайте через `checkStatus()`.

### Колбэк токенизации

> Структура колбэка токенизации **другая** (код результата в корне, данные в `payload`).

```php
$callback = $alif->webhooks()->tokenizationCallback(file_get_contents('php://input'));

if ($callback->isSuccessful()) {
    saveToken($callback->orderId, $callback->token); // сохраните для повторных платежей
}

http_response_code(200);
```

### Проверка того, что присылает Alif

Alif подписывает свои ответы той же HMAC-схемой, строкой `orderId . status . transactionId` — так описана проверка токена ответа в документации, и ровно эти поля приходят в колбэке. Это и проверяется по умолчанию:

```php
$alif->webhooks()->verifyPaymentCallback($callback);            // колбэки
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
    $e->apiCode;        // 208, 400, 401, 403, 404, 500
    $e->apiMessage;     // человекочитаемое сообщение (RU)
    $e->isDuplicate();  // true для кода 208
    $e->isRetryable();  // true для 404 / 500
} catch (TransportException $e) {
    // сетевая ошибка
}
```

| Код | Значение | Повтор |
|------|--------------------|--------|
| 200 | Успешно | — |
| 208 | Дубликат order_id | Нет |
| 400 | Ошибка валидации | Нет |
| 401 | Ошибка авторизации | Нет |
| 403 | Неверный ключ | Нет |
| 404 | Не найдено | Да |
| 500 | Внутренняя ошибка | Да |

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

public function callback(Request $request)
{
    $callback = AlifPay::webhooks()->paymentCallback($request->getContent());

    if ($callback->status === PaymentStatus::Ok) {
        Order::where('reference', $callback->orderId)->update(['status' => 'paid']);
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
// ... проверяйте $transport->lastBody / lastHeaders / lastUrl
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
├── AlifPay.php              # точка входа — payments()/tokenization()/marketplace()/webhooks()/provider()
├── Config.php               # неизменяемая конфигурация
├── Signature.php            # подпись HMAC SHA256 (двойной хэш)
├── Enums/                   # Environment, Gate, PaymentStatus, TokenizationState, ProviderCode, …
├── Http/                    # интерфейс Transport, CurlTransport, Response
├── Requests/                # PaymentRequest, TokenChargeRequest, MarketplaceRequest, TerminalSplit, …
├── Clients/                 # PaymentClient, TokenizationClient, MarketplaceClient
├── Webhook/                 # DTO колбэков + WebhookHandler
├── Provider/                # эндпоинт, который вызывает Alif: check / pay / status
├── Exceptions/              # ApiException, TransportException, …
└── Laravel/                 # ServiceProvider + Facade
```

Документация для разработчика — архитектура, все строки подписи, заметки по каждой области и открытые вопросы в самой документации Alif — лежит в [`docs/dev/`](docs/dev/README.md).

---

## История изменений

См. [CHANGELOG.md](CHANGELOG.md). Версия 2.0.0 меняет поле разбивки маркетплейса и убирает `TerminalAmount` — прочитайте перед обновлением.

---

## Лицензия

MIT © TexHub Pro — разработано Mahmudi Shodmehr.
