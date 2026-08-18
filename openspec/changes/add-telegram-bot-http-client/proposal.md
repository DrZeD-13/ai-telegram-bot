## Why

Приложение — Telegram-бот, но в Infrastructure ещё нет HTTP-клиента Bot API, соответствующего правилам исходящего HTTP (`docs/http-clients.md`). Сценарии Application не могут получать входящие сообщения и отвечать в чат, не связываясь напрямую с HTTP, хостом и токеном. Нужен изолированный транспортный клиент как первая интеграция с Telegram.

## What Changes

- Добавить HTTP-клиент Telegram Bot API в `src/Infrastructure/Transport/Telegram`.
- Реализовать два метода: получить входящие сообщения (`getUpdates`) — результат есть коллекция полученных сообщений — и отправить текстовое сообщение (`sendMessage`).
- Использовать только scoped `HttpClientInterface` (`#[Target('telegram')]`, пакет `symfony/http-client`).
- Вынести origin в `TELEGRAM_API_HOST` и токен в `TELEGRAM_BOT_TOKEN`; не хардкодить хост и не хранить секрет в коде / закоммиченных значениях.
- Описать порт в Application; исключения порта наследуют `CoreException`.
- Маппить ответы Bot API через `*Mapper`.
- Покрыть клиент и mapper’ы unit-тестами по `docs/testing.md`.

## Capabilities

### New Capabilities

- `telegram-bot-http-client`: транспортный клиент Bot API — получение обновлений/сообщений и отправка текста, хост и токен из env, scoped Symfony HttpClient.

### Modified Capabilities

- (нет существующих specs в `openspec/specs/`)

## Impact

- Новые/изменённые классы: порт Application, `TelegramBotHttpClient`, `ApiUrlEnum`, mapper’ы, `CoreException`.
- Зависимость: `symfony/http-client` (уже добавлена).
- Конфигурация: `TELEGRAM_API_HOST`, `TELEGRAM_BOT_TOKEN`; `config/packages/http_clients.yaml` (scoped `telegram`).
- Тесты: `tests/Unit/Infrastructure/Transport/Telegram/` (+ `Mapper/`); coverage-атрибуты. Functional HTTP + snapshot для этого клиента не обязательны. Правила — `docs/testing.md`.
- Вне scope: webhook, polling-цикл, команды бота, обработка callback/media, UI Presentation.
