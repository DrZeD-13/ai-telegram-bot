## 1. Domain persistence

- [x] 1.1 Add required `updateId` to `ProcessedTelegramMessage` (column `update_id`, SQL comment «Идентификатор Telegram update»), constructor argument, and `getUpdateId()`
- [x] 1.2 Add read-only `App\Domain\Repository\ProcessedTelegramMessageRepository` with `findMaxUpdateId(): ?int` and `findOneByChatAndMessageId` only (`@throws` per `docs/exceptions.md`); no `save` / `saveAll`
- [x] 1.3 Implement the domain interface on the Infrastructure repository: `#[AsAlias]` (import interface with alias); `findMaxUpdateId` via `MAX(updateId)`; **remove** `save`
- [x] 1.4 Generate and commit a Doctrine migration for `update_id`

## 2. Unit of Work

- [x] 2.1 Add `App\Application\Port\UnitOfWork` with `persist(object $entity): void` and `flush(): void`, plus Application persistence exception extending `CoreException` on `@throws`
- [x] 2.2 Add Infrastructure Doctrine adapter (`#[AsAlias(UnitOfWork::class)]`) that delegates to `EntityManagerInterface::persist` / `flush` and wraps unknown throwables in the port exception

## 3. Application use case

- [x] 3.1 Add `App\Application\UseCase\ProcessIncomingTelegramMessages` with `execute(): void`; inject `TelegramBotGateway`, `NeuralNetworkGateway`, `App\Domain\Repository\ProcessedTelegramMessageRepository`, `UnitOfWork`, `LoggerService`
- [x] 3.2 Poll: `findMaxUpdateId`; `getMessages(null)` if empty store else `getMessages(max + 1)`; return on empty inbox without flush; do not catch getMessages failures as path 2.1
- [x] 3.3 Chunk retrieved messages by 100; after each processed entity call `UnitOfWork::persist`; after the chunk call `flush` once if anything was persisted (skip empty-text and duplicate chat+message, including in-run Set)
- [x] 3.4 Happy path: `mb_strlen` ≤ 1024; `createChatCompletion` with user text plus `"\nответ сделай не больше 1024 символа"`; model = first `listModels()` id for the run; `sendMessage` AI reply; `markProcessedSuccess` with full user text, `updateId`, user facts, `sentAt`; then persist
- [x] 3.5 Path 2.1: validation >1024 / neural network exception or empty reply / failed send of AI reply — truncate stored text to 1024, `markProcessedError` with the spec strings, `sendMessage` that string; if error notify send fails, log and still persist the entity for the chunk flush

## 4. Presentation

- [x] 4.1 Add `ProcessIncomingTelegramMessagesCommand` (`telegram:process-incoming`) that only calls the use case and maps thrown port/domain exceptions to a non-zero exit code; no Infrastructure imports

## 5. Tests

- [x] 5.1 Update `ProcessedTelegramMessageTest` for `updateId` (`CoversMethod` on constructor/`getUpdateId`)
- [x] 5.2 Add `tests/Unit/Application/UseCase/ProcessIncomingTelegramMessagesTest.php` covering `execute`: empty inbox (no flush); offset +1; N persist and one flush per chunk of 100; skip empty text and duplicate; validation 2.1.1; AI failure 2.1.2; send failure 2.1.3; error notify send failure still persist+flush; success path
- [x] 5.3 Run Unit suite and PHPStan (`src/` + `tests/`) at level max
