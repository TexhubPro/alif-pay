# TexHub · Alif Pay

[English](README.md) · **🌐 Русский**

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012%20%7C%2013-ff2d20.svg)](#laravel)

Чистый, не привязанный к фреймворку PHP SDK для платёжного шлюза **Alif Acquiring (WebCheckout)** — платежи, токенизация и маркетплейс-платежи (split-payment) — с полной поддержкой **Laravel**.

> Работает в чистом PHP и любом фреймворке. Для Laravel — авто-дискавери, конфиг и фасад из коробки.

Основано на официальной документации: <https://docs.acquiring.alif.tj/intro>

---

## ✨ Возможности

- 💳 **Стандартные платежи** — Korti Milli, кошелёк Alif, рассрочка Salom, оплата наличными по инвойсу, Visa/Mastercard
- 🔁 **Токенизация** — привязка карт/кошельков для повторных платежей
- 🛒 **Маркетплейс** — разделение одного платежа между несколькими продавцами, холдирование и подтверждение доставки
- 🔐 **Подпись HMAC SHA256** — точная схема двойного HMAC, которую требует Alif, делается за вас
- 📩 **Вебхуки** — типизированные объекты колбэков + проверка подписи
- 🧩 **Подменяемый HTTP-транспорт** — cURL по умолчанию; можно внедрить свой для тестов
- ✅ **Полностью покрыт тестами**, без обращения к сети
- 🟢 Переключение **Test / Production**

---

## 📦 Установка

```bash
composer require texhub/alif-pay
```

Требования: **PHP ≥ 8.2** с расширениями `curl`, `json` и `hash`.

---

## 🚀 Быстрый старт (чистый PHP)

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

## 🌍 Окружения

| Окружение                 | Базовый URL                |
|---------------------------|----------------------------|
| `Environment::Test`       | `https://test-web.alif.tj` |
| `Environment::Production` | `https://web.alif.tj`      |

В тестовой среде можно использовать тестовые карты Alif для разных сценариев (заблокированная карта, недостаточно средств и т.д.) без движения реальных денег.

---

## 🔐 Авторизация (как работает подпись)

Каждый запрос авторизуется токеном HMAC SHA256 на основе данных запроса:

```
token = HMAC_SHA256( dataToSign, HMAC_SHA256(terminal_password, terminal_id) )
```

SDK сам формирует правильный `dataToSign` для каждой операции:

| Операция                 | `dataToSign`                                     |
|--------------------------|--------------------------------------------------|
| Платёж / Маркетплейс     | `terminal_id + order_id + amount + callback_url` |
| Токенизация              | `terminal_id + phone + gate`                     |
| Подтверждение доставки   | `terminal_id + transaction_id + amount`          |
| Подтверждение VSA/MCR    | `terminal_id + parent_transaction_id`            |

Вручную вызывать подпись не нужно, но при необходимости она доступна через `$alif->signature()`.

---

## 💳 Платежи

### Шлюзы (`Gate`)

| Enum                        | Значение `gate`        | Метод                       |
|-----------------------------|------------------------|-----------------------------|
| `Gate::KortiMilli`          | `korti_milli`          | Нац. карта *(по умолчанию)* |
| `Gate::Wallet`              | `wallet`               | Кошелёк Alif mobi           |
| `Gate::Salom`               | `salom`                | Рассрочка Salom             |
| `Gate::Invoice`             | `invoice`              | Оплата наличными            |
| `Gate::Visa`                | `vsa`                  | Visa                        |
| `Gate::Mastercard`          | `mcr`                  | Mastercard                  |
| `Gate::CybersourceCheckout` | `cybersource_checkout` | Cybersource hosted checkout |

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
$alif->payments()->cancel(transactionId: '789012', amount: '100.50');
```

---

## 🔁 Токенизация

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

---

## 🛒 Маркетплейс (split-payment)

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

Средства холдируются до подтверждения доставки:

```php
// Все методы, кроме Visa/Mastercard:
$alif->marketplace()->confirmDelivery(transactionId: '789013', amount: '300.00');

// Visa / Mastercard:
$alif->marketplace()->confirmVsaMcrDelivery(parentTransactionId: '789012');

// Статус и отмена:
$alif->marketplace()->checkStatus('MP_ORDER_123456');
$alif->marketplace()->cancel(transactionId: '789013', amount: '300.00');
```

---

## 📩 Вебхуки (колбэки)

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

### Колбэк токенизации

> ⚠️ Структура колбэка токенизации **другая** (код результата в корне, данные в `payload`).

```php
$callback = $alif->webhooks()->tokenizationCallback(file_get_contents('php://input'));

if ($callback->isSuccessful()) {
    saveToken($callback->orderId, $callback->token); // сохраните для повторных платежей
}

http_response_code(200);
```

### Примечание о проверке подписи колбэка

Alif подписывает колбэки той же HMAC-схемой, но точный порядок конкатенации для токена **колбэка** не опубликован. `verifyPaymentCallback()` по умолчанию использует `orderId . amount`; если у вашего терминала иначе — передайте свою строку:

```php
$alif->webhooks()->verifyPaymentCallback($callback, dataToSign: $yourString);
// или низкоуровневая проверка:
$alif->webhooks()->verifyToken($yourString, $callback->token);
```

---

## ⚙️ Обработка ошибок

Шлюз всегда отвечает HTTP 200 и бизнес-кодом `code`. SDK превращает любой код, отличный от `200`, в `ApiException`:

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

| Код  | Значение           | Повтор |
|------|--------------------|--------|
| 200  | Успешно            | —      |
| 208  | Дубликат order_id  | Нет    |
| 400  | Ошибка валидации   | Нет    |
| 401  | Ошибка авторизации | Нет    |
| 403  | Неверный ключ      | Нет    |
| 404  | Не найдено         | Да     |
| 500  | Внутренняя ошибка  | Да     |

---

## <a name="laravel"></a>🧩 Laravel

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

## 🧪 Тестирование

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

## 📚 Архитектура

```
src/
├── AlifPay.php              # точка входа — payments()/tokenization()/marketplace()/webhooks()
├── Config.php               # неизменяемая конфигурация
├── Signature.php            # подпись HMAC SHA256 (двойной хэш)
├── Enums/                   # Environment, Gate, TokenizationGate, PaymentStatus, …
├── Http/                    # интерфейс Transport, CurlTransport, Response
├── Requests/                # PaymentRequest, TokenizationRequest, MarketplaceRequest, …
├── Clients/                 # PaymentClient, TokenizationClient, MarketplaceClient
├── Webhook/                 # DTO колбэков + WebhookHandler
├── Exceptions/              # ApiException, TransportException, …
└── Laravel/                 # ServiceProvider + Facade
```

---

## Лицензия

MIT © TexHub Pro — разработано Mahmudi Shodmehr.
