## Why

Приложение — Telegram-бот, но в Infrastructure ещё нет HTTP-клиента Bot API. Сценарии Application не могут получать входящие сообщения и отвечать в чат, не связываясь напрямую с HTTP и токеном. Нужен изолированный транспортный клиент как первая интеграция с Telegram.

## What Changes

- Добавить HTTP-клиент Telegram Bot API в `src/Infrastructure/Transport/Telegram`.
- Реализовать два метода: получить входящие сообщения (`getUpdates`) — результат метода есть коллекция полученных сообщений — и отправить текстовое сообщение (`sendMessage`).
- Использовать только `Symfony\Contracts\HttpClient\HttpClientInterface` (пакет `symfony/http-client`).
- Вынести токен бота в переменную окружения; не хранить секрет в коде и в закоммиченных значениях.
- Описать порт в Application, чтобы Presentation и сценарии не зависели от Infrastructure.
- Покрыть клиент unit-тестами: путь в `tests/Unit/` совпадает с путём класса в `src/` (`docs/architecture.md`).

## Capabilities

### New Capabilities

- `telegram-bot-http-client`: транспортный клиент Bot API — получение обновлений/сообщений и отправка текста, токен из env, Symfony HttpClient.

### Modified Capabilities

- (нет существующих specs в `openspec/specs/`)

## Impact

- Новые классы: порт Application, реализация в `App\Infrastructure\Transport\Telegram`, DTO/коллекции ответов.
- Зависимость: `symfony/http-client`.
- Конфигурация: `TELEGRAM_BOT_TOKEN` в `.env` (пустое значение / placeholder); в конструктор клиента — `#[Autowire('%env(TELEGRAM_BOT_TOKEN)%')]`.
- Тесты: `tests/Unit/Infrastructure/Transport/Telegram/` (зеркало `src/...`); functional для этого клиента не обязательны.
- Вне scope: webhook, polling-цикл, команды бота, обработка callback/media, UI Presentation.
