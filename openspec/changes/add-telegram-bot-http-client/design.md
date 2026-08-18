## Context

Слоистая раскладка уже есть (`docs/architecture.md`): HTTP-клиенты живут в Infrastructure, сценарии зависят от портов Application. Правила исходящего HTTP, исключений, mapper’ов и тестов — `docs/http-clients.md`, `docs/exceptions.md`, `docs/mappers.md`, `docs/testing.md`.

В `src/Infrastructure` есть черновик `TelegramBotHttpClient` с абсолютным URL и inline-маппингом; его нужно привести к этим правилам. Мотивация — proposal.md.

Путь реализации: `Infrastructure/Transport/Telegram`.

## Goals / Non-Goals

**Goals:**

- Порт Application + HTTP-реализация с двумя операциями: получить сообщения, отправить текст.
- `getMessages` возвращает `IncomingTelegramMessageCollection`.
- Единственный HTTP-стек: scoped `HttpClientInterface` (`#[Target('telegram')]`).
- Origin из `TELEGRAM_API_HOST`, токен из `TELEGRAM_BOT_TOKEN` (`#[Autowire]`).
- `ApiUrlEnum`, `final readonly` клиент, mapper’ы ответа API.
- Исключения порта наследуют `CoreException`; `@throws` на порте.
- Unit-тесты по `docs/testing.md`: зеркало пути + `CoversClass`/`CoversMethod`.

**Non-Goals:**

- Webhook, long-polling daemon, console-команда опроса.
- Media, клавиатуры, callback, edit/delete, `getMe`.
- Use case «ответить на входящее» в Application/Presentation.
- Functional-тесты клиента (ядро Symfony, реальный Telegram).
- Serializer named-instance / login+cache (у Telegram токен в path).

## Decisions

### 1. Порт в Application, клиент в Infrastructure

**Выбор:** `App\Application\Port\TelegramBotGateway`: `getMessages(?int $offset = null): IncomingTelegramMessageCollection` и `sendMessage(...): SentTelegramMessage`. Реализация `App\Infrastructure\Transport\Telegram\TelegramBotHttpClient`. `#[AsAlias(TelegramBotGateway::class)]`, без yaml-arguments.

**Почему:** Presentation и use case не имеют права `use App\Infrastructure\...`.

**Альтернатива:** только конкретный класс в Infrastructure — ломает слои.

### 2. Каталог `Transport/Telegram`

**Выбор:** `src/Infrastructure/Transport/Telegram/` (`TelegramBotHttpClient`, `ApiUrlEnum`, `Mapper/`). Имя клиента оставляем `TelegramBotHttpClient` (уже в change), а не `{Integration}ApiClient` из примера docs.

**Альтернатива:** `Infrastructure/Telegram/` без `Transport/` — отклонено: это HTTP-транспорт.

### 3. Методы Bot API

**Выбор:**

- Получить сообщения → `getUpdates`. Параметры: optional `offset`, `timeout=0` (без long poll). Из `result[]` только элементы с `message` (`edited_message` не включать).
- Отправить → `sendMessage` с `chat_id` и `text`.

DTO на порте: `IncomingTelegramMessage` + `IncomingTelegramMessageCollection`. У элемента есть `updateId`; next offset считает вызывающий как `max(updateId)+1`. Отправка возвращает `SentTelegramMessage`.

**Альтернатива:** сырые Update или long poll `timeout=30` — отклонены.

### 4. HttpClient: scoped client + enum

**Выбор:** `config/packages/http_clients.yaml` — default JSON headers, timeout, `retry_failed` как в `docs/http-clients.md`; scoped `telegram` с `base_uri: '%env(resolve:TELEGRAM_API_HOST)%'`.

Клиент:

```php
public function __construct(
    #[Target('telegram')]
    private HttpClientInterface $httpClient,
    #[Autowire('%env(TELEGRAM_BOT_TOKEN)%')]
    private string $botToken,
) {}
```

`ApiUrlEnum` (строка `METHOD:/path`):

- getUpdates → `POST:/bot{token}/getUpdates`
- sendMessage → `POST:/bot{token}/sendMessage`

Запрос: `method()` + `uri()`, `vars` для `{token}`, `query` через `array_filter`, тело `json`. Не собирать `https://api.telegram.org` в PHP. Токен только в path Bot API (исключение из docs).

Пакет: `symfony/http-client` (уже в проекте).

**Альтернатива:** голый `HttpClientInterface` без Target и абсолютный URL — запрещено `docs/http-clients.md`.

### 5. Env

**Выбор:** в `.env` и `.docker.loc/.env.example`:

```
TELEGRAM_API_HOST=
TELEGRAM_BOT_TOKEN=
```

Живые значения — `.env.local`. Пустой токен (и пустой хост, если клиент его видит) — `TelegramBotConfigurationException` до HTTP.

**Альтернатива:** один env с полным `https://api.telegram.org/bot<token>` — смешивает хост и секрет.

### 6. Ошибки

**Выбор:** `App\Domain\Exception\CoreException`. Исключения порта в Application наследуют его: configuration (пустой токен/хост), validation (blank text), transport (`ok: false` с `description`, HTTP/сеть). На методах порта — `@throws` только этих типов.

Реализация порта: `catch (CoreException) { throw $e; } catch (Throwable) { wrap into transport (or the declared type) with previous }`. Не пропускать HttpClient/JSON/`\Error`.

**Альтернатива:** Infrastructure-исключения на порте — запрещено `docs/exceptions.md`.

### 7. Mapper’ы

**Выбор:** `Infrastructure/Transport/Telegram/Mapper/*Mapper` с `map`. Клиент не копирует вложенные поля JSON в DTO. Вложенный chat/message — отдельный mapper, внедряется в родительский. Конструктор mapper’а — только другие mapper’ы.

**Альтернатива:** маппинг в клиенте — запрещено `docs/mappers.md` / `docs/http-clients.md`.

### 8. Тесты

**Выбор:** PHPUnit + `MockHttpClient` / `MockResponse`.

- клиент: `tests/Unit/Infrastructure/Transport/Telegram/TelegramBotHttpClientTest.php` — `CoversClass` + `CoversMethod` на `getMessages`/`sendMessage`; сценарии: getUpdates, non-message, empty, send, `ok: false`, HTTP failure, пустой токен, blank text. После смены конструктора — поправить фабрику клиента в тесте.
- каждый mapper: `tests/Unit/Infrastructure/Transport/Telegram/Mapper/{Name}MapperTest.php` — `CoversClass` / `CoversMethod` для `map`.

`requireCoverageMetadata`. Snapshot не для этого unit-клиента. Suites `Unit` / `Functional`; `tests/System` не suite.

**Альтернатива:** тесты в корне `tests/` — запрещено `docs/testing.md`.

## Risks / Trade-offs

- [getUpdates без хранения offset] → дубли, если вызывающий не передаёт offset; это будущий use case.
- [timeout=0] → частый опрос; не цель этого change.
- [Токен в path] → стандарт Telegram; не логировать полный URL.
- [Пустой `TELEGRAM_API_HOST` в контейнере] → scoped `base_uri` может сломаться на compile; fail-closed в клиенте плюс пустой placeholder в committed env.

## Migration Plan

1. Env хоста + `http_clients.yaml`.
2. `CoreException`, `@throws`, `final readonly` + Target + `ApiUrlEnum`.
3. Mapper’ы, вынести маппинг из клиента.
4. Обновить unit-тесты; phpstan `src/` + `tests/`.

Откат: удалить scoped-клиент, env хоста, mapper’ы; вернуть прежний клиент только если change откатывается целиком.

## Open Questions

Нет.
