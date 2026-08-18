# Логи

Правила слоёв — в [architecture.md](architecture.md). Исключения — в [exceptions.md](exceptions.md). Исходящие HTTP-клиенты — в [http-clients.md](http-clients.md).

Прикладной код не логирует через сырой Monolog. Единая точка — `App\Application\Core\Logger\LoggerService` (PSR-3). Он нормализует message/context (скаляры, iterable, `Throwable`, DTO через serializer) и прокидывает запись в Monolog.

## Размещение

```
src/Application/Logger/
  LoggerService.php
  LogContextBuilder.php
```

`LoggerService` — сервис Application. Его можно внедрять в Application, Infrastructure и Presentation. `LogContextBuilder` — статические хелперы контекста; из прикладного кода обычно не вызывается напрямую.

## DI

В `config/services.yaml`:

- `LoggerService` получает внутренний `$logger: '@monolog.logger'` (не самого себя).
- `Psr\Log\LoggerInterface` — alias на `LoggerService`.

Инжектить лучше `LoggerService`: на нём есть `logException`, `addAdditionalContext`, `deleteContextKey`. `LoggerInterface` тоже резолвится в этот сервис, но без дополнительных методов.

Не инжектить `@monolog.logger` в сценарии и контроллеры.

## Что писать

Сообщение — короткая фраза, что произошло (на русском, как в остальном коде). Детали — в `context`, не в конкатенации строки.

| Уровень | Когда |
|---------|--------|
| `debug` | временная диагностика, внутренние шаги |
| `info` | штатный ход: старт/успех операции, исходящий HTTP (без секретов) |
| `notice` | заметное, но не ошибка |
| `warning` | сбой, от которого сценарий ещё может отойти |
| `error` / `logException` | операция провалилась, исключение, ответ API `ok: false` |
| `critical` / `emergency` | сервис не может продолжать работу |

Исключение логировать через `logException`, не через `error((string) $e)` и не дублировать stack trace вручную.

```php
public function __construct(
    private readonly LoggerService $logger,
) {
}

$this->logger->info('Запрошены входящие сообщения Telegram', [
    'offset' => $offset,
]);

try {
    // ...
} catch (Throwable $exception) {
    $this->logger->logException(
        'Не удалось отправить сообщение в Telegram',
        $exception,
        [
            'chatId' => $chatId,
        ],
    );

    throw $exception;
}
```

`LogContextBuilder::makeExceptionContext` добавляет тип, файл, строку, код; для `HttpExceptionInterface` — status, headers и обрезку тела ответа (до 1024 символов).

## Контекст запроса

Общие поля на все последующие записи в этом процессе:

```php
$this->logger->addAdditionalContext('updateId', (string) $updateId);
// ...
$this->logger->deleteContextKey('updateId');
```

Ключ — короткое имя (`userId`, `chatId`, `updateId`). Значение — строка. Не класть токен бота, пароли, `auth_bearer`, полный URL с секретом.

## HTTP-клиенты

Перед запросом — `info` с URL enum и безопасными опциями. На ошибке — `logException` или `error` с контекстом исключения. Не логировать сырой response с ПДн и не dump’ить `TELEGRAM_BOT_TOKEN` / полный `bot{token}/…`.

Подробности клиента — [http-clients.md](http-clients.md).

## Не делать

- Писать в Monolog напрямую (`LoggerInterface` из канала, минуя alias, или `@monolog.logger` в прикладном коде).
- Логировать секреты, персональные данные «как пришли», тела запросов с токеном.
- Склеивать большой JSON в message вместо context.
- Глотать исключение после `logException` без повторного throw / без ответа канала, если сценарий должен упасть.
- Вызывать `LogContextBuilder` из контроллера вместо `LoggerService`.
