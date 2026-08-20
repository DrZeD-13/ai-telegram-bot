## 1. Domain persistence

- [x] 1.1 Add required `updateId` to `ProcessedTelegramMessage` (column `update_id`, SQL comment «Идентификатор Telegram update»), constructor argument, and `getUpdateId()`
- [x] 1.2 Add read-only `App\Domain\Repository\ProcessedTelegramMessageRepository` with `findMaxUpdateId(): ?int` and `findOneByChatAndMessageId` only (`@throws` per `docs/exceptions.md`); no `save` / `saveAll`
- [x] 1.3 Implement the domain interface on the Infrastructure repository: `#[AsAlias]` (import interface with alias); `findMaxUpdateId` via `MAX(updateId)`; **remove** `save`
- [x] 1.4 Generate and commit a Doctrine migration for `update_id`

## 2. Unit of Work

- [x] 2.1 Add `App\Application\Port\UnitOfWork` with `persist(object $entity): void` and `flush(): void`; `@throws PersistenceException` only
- [x] 2.2 Add `App\Application\Exception\PersistenceException` extending `CoreException`
- [x] 2.3 Add `App\Infrastructure\Persistence\DoctrineUnitOfWork` (`#[AsAlias(UnitOfWork::class)]`): `persist`/`flush` via `EntityManagerInterface`; wrap unknown throwables in `PersistenceException`; do not `clear()` after flush
- [x] 2.4 Use case must not call EntityManager or the Doctrine repository `save`; register each processed entity with `UnitOfWork::persist` during the chunk; call `flush` **once** after the chunk if at least one persist happened; skip flush when the chunk produced no entities
- [x] 2.5 Repository remains read-only (no `saveAll`); duplicates and empty text are not persisted

## 3. Application use case

- [x] 3.1 Add `App\Application\UseCase\ProcessIncomingTelegramMessages` with `execute(): void`; inject `TelegramBotGateway`, `NeuralNetworkGateway`, `App\Domain\Repository\ProcessedTelegramMessageRepository`, `UnitOfWork`, `LoggerService`
- [x] 3.2 Poll: `findMaxUpdateId`; `getMessages(null)` if empty store else `getMessages(max + 1)`; return on empty inbox without flush; do not catch getMessages failures as path 2.1
- [x] 3.3 Chunk retrieved messages by 100; skip empty-text and duplicate chat+message (DB + in-run set) in `execute`; do not pass parameters by reference into `processIncomingMessage`
- [x] 3.4 Resolve model once per run only after text length ≤ 1024: first `listModels()` id; pass `?string $modelId` by value into `processIncomingMessage`
- [x] 3.5 Happy path: `createChatCompletion` with user text plus `"\nответ сделай не больше 1024 символа"`; `sendMessage(int $chatId, …)` AI reply; `markProcessedSuccess` with full user text, `updateId`, user facts, `sentAt`; then `UnitOfWork::persist`
- [x] 3.6 Path 2.1: validation >1024 / neural network exception or empty reply / failed send of AI reply — truncate stored text to 1024, `markProcessedError` with the spec strings, `sendMessage` that string; persist the entity for the chunk flush even if error notify send fails

## 4. Logging

- [x] 4.1 After a non-empty neural network reply: `LoggerService::info` «Нейросеть вернула ответ» with context `userId`, `message` (user text), `response` (model text); do not log on empty reply or `NeuralNetworkException` (those go to path 2.1)
- [x] 4.2 After successful `sendMessage` of the user-facing error text: `LoggerService::info` «Пользователю отправлено сообщение об ошибке обработки» with `userId`, `chatId`, `errorText`
- [x] 4.3 If error notify `sendMessage` fails: `LoggerService::logException` «Не удалось отправить пользователю сообщение об ошибке обработки» with `chatId`, `messageId`, `updateId`; still persist the error entity
- [x] 4.4 Log via `LoggerService` only (`docs/logging.md`): short Russian message, details in context, no Monolog in the use case

## 5. Presentation

- [x] 5.1 Add `ProcessIncomingTelegramMessagesCommand` (`telegram:process-incoming`); Presentation must not `use App\Infrastructure\...`
- [x] 5.2 Run as a worker: infinite loop calling `processIncomingTelegramMessages->execute()`, then `sleep(60)`; on `CoreException` print the error and continue the loop
- [x] 5.3 Single-instance lock: `LockableTrait` + injected `LockFactory`; if lock is not acquired, warn that the command is already running and return `FAILURE`; release the lock in `finally`
- [x] 5.4 Add `symfony/lock` 7.4, `config/packages/lock.yaml`, `LOCK_DSN=flock` in env templates (`.env.dev`, `.env.test`, `.docker.loc/.env.example`)

## 6. Telegram port and local fixtures

- [x] 6.1 Narrow `TelegramBotGateway::sendMessage` to `int $chatId` only (HTTP client, fixture gateway, selector); `TelegramChat::$id` is `int`; mapper accepts int or numeric string (e.g. `"-100123"`) and casts to int
- [x] 6.2 Add `TELEGRAM_USE_FIXTURES` (`env(TELEGRAM_USE_FIXTURES): 'false'` in `services.yaml`; `%env(bool:TELEGRAM_USE_FIXTURES)%` without `default:false` processor); `TelegramBotGatewaySelector` as `#[AsAlias(TelegramBotGateway::class)]` delegates to fixture or HTTP client
- [x] 6.3 Add `TelegramBotFixtureGateway`: read `fixtures/telegram/get_updates.json`, honor offset (`update_id >= offset`), write outgoing messages to `var/telegram-fixtures/sent.json` instead of Bot API
- [x] 6.4 Fixture file covers short AI question, callback skip, message without text, text > 1024, second short question

## 7. Tests

- [x] 7.1 Update `ProcessedTelegramMessageTest` for `updateId` (`CoversMethod` on constructor/`getUpdateId`)
- [x] 7.2 Add `tests/Unit/Application/UseCase/ProcessIncomingTelegramMessagesTest.php` covering `execute`: empty inbox (no flush); offset +1; N persist and one flush per chunk of 100; skip empty text and duplicate; validation 2.1.1; AI failure 2.1.2; send failure 2.1.3; error notify send failure still persist+flush; success path
- [x] 7.3 Add unit tests for `TelegramBotFixtureGateway`, `TelegramBotGatewaySelector`, and string-to-int chat id in `TelegramChatMapperTest`
- [x] 7.4 Run Unit suite and PHPStan (`src/` + `tests/`) at level max
