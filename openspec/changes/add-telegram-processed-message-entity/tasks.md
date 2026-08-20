## 1. Domain model

- [ ] 1.1 Add string-backed enum `App\Domain\Entity\ProcessedTelegramMessageStatus`: `Pending` (`pending`), `ProcessedSuccess` (`processed_success`), `ProcessedError` (`processed_error`)
- [ ] 1.2 Add domain exception (extends `CoreException`) for empty error text when marking processed-with-error
- [ ] 1.3 Add Doctrine entity `App\Domain\Entity\ProcessedTelegramMessage` mapped to table `processed_telegram_message` with fields from design.md (bigint `chatId`, unique index `uniq_processed_telegram_message_chat_message` on `chat_id` + `message_id`); table and column `options.comment` per design.md (skip `id`, `createdAt`, `updatedAt`)
- [ ] 1.4 Constructor: required `chatId`, `messageId`, `sentAt`; optional user names, nickname, text; status `Pending`; `errorText` null; set `createdAt`/`updatedAt`; `PrePersist`/`PreUpdate` for timestamps
- [ ] 1.5 Add `markProcessedSuccess()` (status success, clear error) and `markProcessedError(string $errorText)` (non-empty text or throw); getters for persisted fields

## 2. Persistence

- [ ] 2.1 Add `App\Infrastructure\Persistence\Repository\ProcessedTelegramMessageRepository` (`ServiceEntityRepository`) with `save` and `findOneByChatAndMessageId`
- [ ] 2.2 Generate Doctrine migration for the new table, unique index, and SQL comments from design.md; commit the migration file
- [ ] 2.3 Keep `Domain/Entity` excluded from DI in `services.yaml`; do not register the entity as a service

## 3. Tests

- [ ] 3.1 Unit test `tests/Unit/Domain/Entity/ProcessedTelegramMessageTest.php` with `CoversClass` / `CoversMethod`: new record is pending with empty error; success clears error; error stores text; empty error text throws
- [ ] 3.2 Unit test for the status enum if it has methods beyond cases; otherwise cover enum via entity tests only
- [ ] 3.3 Run Unit suite and PHPStan (`src/` + `tests/`) at level max
