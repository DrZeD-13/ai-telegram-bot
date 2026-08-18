## Context

Слоистая раскладка уже есть (`docs/architecture.md`): HTTP-клиенты живут в Infrastructure, сценарии зависят от портов Application. В `src/Infrastructure` пока только Persistence; каталога Telegram нет. `symfony/http-client` в `composer.json` отсутствует. Токен в `.env` / `.docker.loc/.env.example` не объявлен. Мотивация — proposal.md.

Путь реализации: `Infrastructure/Transport/Telegram` (как в примере `docs/architecture.md` для HTTP/внешних API).

## Goals / Non-Goals

**Goals:**

- Порт Application + HTTP-реализация с двумя операциями: получить сообщения, отправить текст.
- `getMessages` возвращает `IncomingTelegramMessageCollection` (коллекция полученных сообщений).
- Единственный HTTP-стек: `HttpClientInterface`.
- Токен только из `TELEGRAM_BOT_TOKEN`, в конструктор через `#[Autowire]`.
- Типизированные DTO и коллекция по правилам архитектуры (не `array`).
- Unit-тесты клиента по раскладке `docs/architecture.md`: `tests/Unit/` + зеркало пути класса.

**Non-Goals:**

- Webhook, long-polling daemon, console-команда опроса.
- Media, клавиатуры, callback, edit/delete, `getMe`.
- Use case «ответить на входящее» в Application/Presentation.
- Functional-тесты клиента (ядро Symfony, реальный Telegram).

## Decisions

### 1. Порт в Application, клиент в Infrastructure

**Выбор:** интерфейс `App\Application\Port\TelegramBotGateway` (или эквивалентное имя порта): `getMessages(?int $offset = null): IncomingTelegramMessageCollection` и `sendMessage(...): SentTelegramMessage`. Реализация `App\Infrastructure\Transport\Telegram\TelegramBotHttpClient`. Регистрация через autoconfigure: класс реализует порт и помечается `#[AsAlias(TelegramBotGateway::class)]`, без yaml-arguments.

**Почему:** Presentation и будущие use case не имеют права `use App\Infrastructure\...`. Сам HTTP остаётся за портом.

**Альтернатива:** только конкретный класс в Infrastructure — быстрее, но ломает правило слоёв при первом же вызове из команды/контроллера.

### 2. Каталог `Transport/Telegram`

**Выбор:** `src/Infrastructure/Transport/Telegram/`. Внутри — `Client`, DTO маппинга ответа, исключение транспорта. Группировка `Transport/` для внешних HTTP API, имя системы — `Telegram`.

**Альтернатива:** `Infrastructure/Telegram/` без `Transport/` — тоже согласовано с таблицей architecture.md; выбран явный транспортный слой, потому что клиент — HTTP, не домен.

### 3. Методы Bot API

**Выбор:**

- Получить сообщения → `POST/GET https://api.telegram.org/bot<token>/getUpdates`. Параметры: `offset` (optional), короткий `timeout` (0 или небольшой, без long poll в этом change). Из `result[]` брать только элементы с `message` (или `edited_message` не включать в v1).
- Отправить → `.../sendMessage` с `chat_id` и `text`.

DTO на границе порта (Application): элемент `IncomingTelegramMessage` и коллекция `IncomingTelegramMessageCollection` рядом с ним (правило `docs/architecture.md`: набор не возвращается как `array`). `getMessages` возвращает именно эту коллекцию — не `GetMessagesResult`, не `iterable`, не сырые Update. У каждого элемента есть `updateId`; next offset считает вызывающий как `max(updateId)+1`. Отправка возвращает одиночный `SentTelegramMessage`, не коллекцию.

**Альтернатива:** вернуть сырые Update — лишняя поверхность API; long poll `timeout=30` — блокирует воркеры php-fpm, откладываем.

### 4. HttpClient

**Выбор:** constructor injection `HttpClientInterface` + string `$botToken`. Запросы относительные к `https://api.telegram.org/bot{token}/`. `json()` decode, проверка `ok`. Не использовать SDK `telegram-bot-sdk` / Guzzle напрямую.

**Альтернатива:** обёртка поверх nutgram/irazasyed — лишняя зависимость и обход явного требования.

Пакет: `composer require symfony/http-client` (Flex подключит `http_client` config при необходимости).

### 5. Токен и DI

**Выбор:** autowire атрибутами на классе, без `arguments` в `config/services.yaml`. `HttpClientInterface` подхватывается контейнером сам. Токен:

```php
public function __construct(
    private HttpClientInterface $httpClient,
    #[Autowire('%env(TELEGRAM_BOT_TOKEN)%')]
    private string $botToken,
) {}
```

Порт: `#[AsAlias(TelegramBotGateway::class)]` на реализации. В `.env` и `.docker.loc/.env.example` ключ `TELEGRAM_BOT_TOKEN=` без значения. Реальный секрет — `.env.local` (не коммитить). Опционально `parameters.env(TELEGRAM_BOT_TOKEN): ''` в yaml, если нужен default при пустом env на этапе компиляции контейнера.

**Альтернатива:** явный `arguments.$botToken` у сервиса — отклонено, это дублирует autowire.

Пустой токен: в начале `getMessages`/`sendMessage` бросать `TelegramBotConfigurationException`, не ходить в сеть.

### 6. Ошибки

**Выбор:** иерархия в Infrastructure, но порт объявляет `TelegramBotTransportException` (или маркер в Application) чтобы сценарии ловили без зависимости от HTTP. `ok: false` → то же исключение с `description` из JSON. HttpExceptionInterface / TransportExceptionInterface → wrap.

### 7. Тесты

**Выбор:** PHPUnit (`phpunit/phpunit` или `symfony/phpunit-bridge`) + `MockHttpClient` / `MockResponse`. Файл строго:

`tests/Unit/Infrastructure/Transport/Telegram/TelegramBotHttpClientTest.php`

(`App\Tests\Unit\Infrastructure\Transport\Telegram\TelegramBotHttpClientTest`). Покрыть: разбор getUpdates, фильтр non-message, empty collection, sendMessage, `ok: false`, HTTP failure, пустой токен, blank text. Без реальных вызовов Telegram и без `WebTestCase`/kernel.

PHPUnit: отдельные suites `Unit` → `tests/Unit` и `Functional` → `tests/Functional` (каталог Functional можно оставить пустым placeholder, если тестов ещё нет).

**Альтернатива:** `tests/TelegramBotHttpClientTest.php` или `tests/Infrastructure/...` без `Unit/` — запрещено architecture.md.

## Risks / Trade-offs

- [getUpdates без хранения offset] → клиент не персистит offset; это ответственность будущего use case. Риск дублей, если вызывающий не передаёт offset.
- [timeout=0] → нужно часто опрашивать; не цель этого change.
- [Токен в URL пути Bot API] → стандарт Telegram; не логировать полный URL. При логировании HttpClient включить redaction / не dump request URL в prod.
- [PHPUnit ещё нет в проекте] → появление test-suite увеличивает composer; без тестов регрессии JSON-маппинга дешёвые.

## Migration Plan

1. `composer require symfony/http-client` (и phpunit, если добавляем тесты).
2. Порт, DTO, клиент, исключения, DI, env.
3. `bin/console debug:container` / phpstan на новые классы.

Откат: удалить пакет, классы, параметр env.

## Open Questions

Нет. Long poll, webhook и use case опроса отложены сознательно.
