## Context

Слои: Presentation → Application → Domain; Infrastructure реализует порты (`docs/architecture.md`). Application не имеет права зависеть от Doctrine / EntityManager. Уже есть `TelegramBotGateway::getMessages(?int $offset)` / `sendMessage`, `NeuralNetworkGateway::createChatCompletion`, entity `ProcessedTelegramMessage`. Doctrine-репозиторий сейчас содержит `save` (`persist`+`flush` на запись) — это снимаем. `IncomingTelegramMessage` уже несёт `updateId`. Мотивация — proposal.md; поведение — specs/telegram-ai-inbound-processing/spec.md.

## Goals / Non-Goals

**Goals:**

- Use case в Application: poll → валидация → AI → ответ → статусы.
- Read-only доменный репозиторий: курсор `update_id`, поиск дубликата. Без `save` / `saveAll`.
- Запись в БД только через Unit of Work: `persist` ставит сущность в identity map, `flush` один раз после чанка.
- Поле `updateId` на entity + миграция.
- CLI `telegram:process-incoming`.
- Unit-тесты use case со стабами портов по `docs/testing.md`.

**Non-Goals:**

- Webhook, Messenger, long-polling `timeout` > 0, цикл daemon.
- История диалога / system prompt кроме суффикса про 1024 символа.
- Обрезка ответа модели; второй провайдер нейросети.
- Исправление орфографии пользовательских текстов ошибок из spec.
- Транзакции вручную (`begin`/`commit`) сверх Doctrine UoW `flush`.

## Decisions

### 1. Курсор — `update_id`, не `message_id` и не PK

**Выбор:** хранить Telegram `update_id` на `ProcessedTelegramMessage`. Offset для `getMessages`: `null`, если записей нет; иначе `max(update_id) + 1`.

Запрос сформулирован как «последний id сообщения», но Bot API `getUpdates` принимает идентификатор **update**, а `message_id` уникален только в чате и не монотонен глобально. DTO уже содержит `updateId`.

Альтернатива: отдельная таблица курсора — лишняя сущность при одном боте. Альтернатива: `message_id` как offset — отклонено.

Колонка `update_id` (int, not null), SQL comment «Идентификатор Telegram update». Уникальность по-прежнему `(chat_id, message_id)`.

### 2. Репозиторий только на чтение

**Выбор:** `App\Domain\Repository\ProcessedTelegramMessageRepository` — только запросы:

- `findMaxUpdateId(): ?int`
- `findOneByChatAndMessageId(int $chatId, int $messageId): ?ProcessedTelegramMessage`

Методов записи нет. Существующий `save` на Infrastructure-классе **удалить**.

Реализация: тот же `ServiceEntityRepository`, `#[AsAlias]` на доменный интерфейс; в PHP-файле alias имени интерфейса (совпадает с классом Infrastructure).

Альтернатива `saveAll(...$messages)` на репозитории — отклонена: репозиторий не владеет commit.

### 3. Unit of Work — единственная запись в БД

**Выбор:** порт `App\Application\Port\UnitOfWork` (контракт сценария/персистентности, не агрегат):

- `persist(object $entity): void` — зарегистрировать сущность в UoW (без SQL)
- `flush(): void` — один commit накопленных изменений

Реализация: `App\Infrastructure\Persistence\DoctrineUnitOfWork` (или рядом с Repository), обёртка `EntityManagerInterface`: `persist` / `flush`. Неизвестные throwable оборачивать в Application-исключение-наследник `CoreException` (например `PersistenceException`), объявить в `@throws` порта. `#[AsAlias(UnitOfWork::class)]`.

Use case **не** вызывает EntityManager.

После чанка, в котором был хотя бы один `persist`: один `flush()`. Если в чанке нечего регистрировать — `flush` не вызывать. После успешного `flush` можно `EntityManager::clear()` внутри реализации `flush` **не** делать по умолчанию (clear сбросит identity map и сломает повторный find в том же запросе); clear не обязателен в этом change.

Альтернатива: `Flusher` только с `flush()`, а `persist` через репозиторий — отклонена (репозиторий read-only). Альтернатива: `saveAll` — отклонена.

### 4. Use case

**Выбор:** `App\Application\UseCase\ProcessIncomingTelegramMessages` с `execute(): void`.

Зависимости: `TelegramBotGateway`, `NeuralNetworkGateway`, `ProcessedTelegramMessageRepository`, `UnitOfWork`, `LoggerService`.

Presentation: `ProcessIncomingTelegramMessagesCommand` (`telegram:process-incoming`) только вызывает `execute()`. Без `use App\Infrastructure\...`.

Алгоритм `execute`:

1. `maxUpdateId = findMaxUpdateId()`; `offset = maxUpdateId === null ? null : maxUpdateId + 1`.
2. `getMessages(offset)`. Пустая коллекция → выход без `flush`. Сбой getMessages → проброс (не ветка 2.1).
3. Чанки по 100 (`array_chunk`).
4. Для каждого сообщения чанка: обработка; если есть итоговая entity — `unitOfWork->persist($entity)` сразу после обработки этого сообщения (SQL ещё нет). Skip без текста и дубликаты (БД или in-run `Set` `{chatId}:{messageId}`) — без persist.
5. После цикла по чанку: если в чанке был хотя бы один persist — `unitOfWork->flush()` один раз.

Ветка с текстом:

- `mb_strlen($text) > 1024` → 2.1 валидация, без AI.
- иначе `createChatCompletion` с `ChatMessage('user', $text . "\nответ сделай не больше 1024 символа")`. Модель один раз за `execute`: первый id из `listModels()`; нет моделей → 2.1 как сбой нейросети на каждом валидном сообщении.
- Исключения `NeuralNetwork*` и пустой `text` результата → 2.1 API.
- Успешный `sendMessage` ответа → `markProcessedSuccess()`, затем persist.
- Сбой send ответа → 2.1 доставка.

Ветка 2.1: entity с фактами пользователя, `mb_substr` текста до 1024, `markProcessedError` тем же текстом, что в чат; send ошибки — сбой глотаем (лог), persist всё равно.

Константы: лимит 1024, чанк 100, суффикс AI, три строки ошибок **дословно** из spec.

### 5. Entity

Конструктор: обязательный `int $updateId`, геттер `getUpdateId()`. Статусы без изменений.

### 6. Тесты use case

`tests/Unit/Application/UseCase/ProcessIncomingTelegramMessagesTest.php`: моки Telegram, NN, read-only репозитория, UnitOfWork. Сценарии: пустой inbox (нет flush); offset +1; чанк 100 — N× persist, 1× flush на чанк; skip без текста и дубликат; валидация / AI / send 2.1; сбой notify всё равно persist+flush чанка; успех. `CoversClass` / `CoversMethod` на `execute`.

## Risks / Trade-offs

- [Ответ уже ушёл в Telegram, flush чанка упал] → повтор при следующем poll. Mitigation: уникальность chat+message после успешного flush; риск принят (один commit на чанк).
- [Пользователь ждал offset = message_id] → неверный poll. Mitigation: `update_id`.
- [getUpdates без offset на пустой БД] → возможна история inbox. Mitigation: первый запуск.
- [Два CLI сразу] → вне scope; уникальный индекс снижает дубликаты строк.

## Migration Plan

1. `update_id` на entity; read-only доменный репозиторий; удалить `save`; порт и реализация UnitOfWork; use case; CLI; тесты.
2. Doctrine-миграция на колонку.
3. Откат: `migrate prev` для колонки; код UoW/репозитория откатывается вместе с change.

## Open Questions

Нет: модель AI — первая из `listModels()` (как `ai:ask`).
