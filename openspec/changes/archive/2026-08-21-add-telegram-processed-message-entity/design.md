## Context

Doctrine ORM уже настроен на `src/Domain/Entity` (`docs/architecture.md`, `config/packages/doctrine.yaml`). Сущностей в домене ещё нет, миграций нет. БД — MySQL/MariaDB (`DATABASE_URL`). Telegram chat id бывает отрицательным и больше 32 бит. Мотивация — proposal.md; требования — specs/telegram-processed-message/spec.md.

## Goals / Non-Goals

**Goals:**

- Doctrine-entity в `Domain/Entity` с атрибутным маппингом.
- Backed PHP enum статуса с тремя значениями.
- Миграция таблицы и уникальный индекс `(chat_id, message_id)`.
- SQL-комментарии на таблице и колонках (кроме `id`, `created_at`, `updated_at`).
- Doctrine-репозиторий в `Infrastructure/Persistence/Repository` для save/find по паре chat+message.

**Non-Goals:**

- Use case обработки, poll `getUpdates`, webhook, вызов нейросети, ответ в чат.
- Отдельная сущность пользователя Telegram; telegram user id в этом change не храним (в запросе его нет).
- Domain-порт репозитория: до появления сценария достаточно Doctrine-репозитория. Сценарий позже получит порт Application/Domain и будет зависеть от него, не от Infrastructure.

## Decisions

### 1. Имена типов и таблицы

**Выбор:** класс `App\Domain\Entity\ProcessedTelegramMessage`, таблица `processed_telegram_message`. Enum `App\Domain\Entity\ProcessedTelegramMessageStatus` рядом с entity (отдельного `Domain/Enum` в каркасе нет).

Альтернатива: `TelegramIncomingMessage` — хуже отражает статус обработки.

### 2. Поля entity

| Поле | Тип | Nullable | Смысл |
| --- | --- | --- | --- |
| `id` | int, autoincrement | нет | суррогатный PK |
| `userFirstName` | string | да | имя |
| `userLastName` | string | да | фамилия |
| `userNickname` | string | да | username Telegram |
| `chatId` | bigint (`string` или `int` в PHP — **bigint как `int` на 64-bit PHP**) | нет | id чата |
| `messageId` | int | нет | id сообщения |
| `text` | text | да | текст |
| `sentAt` | `DateTimeImmutable` | нет | дата отправки в Telegram (из unix `date`) |
| `status` | enum Doctrine | нет | статус |
| `errorText` | text | да | текст ошибки |
| `createdAt` | `DateTimeImmutable` | нет | создание записи |
| `updatedAt` | `DateTimeImmutable` | нет | изменение записи |

Уникальный индекс `uniq_processed_telegram_message_chat_message` на `(chat_id, message_id)`.

### 2.1 SQL-комментарии (MySQL COMMENT)

Задавать через Doctrine `options: ['comment' => '...']` на `#[ORM\Table]` и на `#[ORM\Column]`, чтобы миграция их подхватила. Язык комментариев — русский.

| Объект | Комментарий |
| --- | --- |
| таблица `processed_telegram_message` | Входящие сообщения Telegram и статус их обработки |
| `user_first_name` | Имя отправителя в Telegram |
| `user_last_name` | Фамилия отправителя в Telegram |
| `user_nickname` | Username отправителя в Telegram |
| `chat_id` | Идентификатор чата Telegram |
| `message_id` | Идентификатор сообщения в чате Telegram |
| `text` | Текст входящего сообщения |
| `sent_at` | Дата и время отправки сообщения в Telegram |
| `status` | Статус обработки: не обработан, успешно, с ошибкой |
| `error_text` | Текст ошибки, если обработка завершилась с ошибкой |

Без комментария: `id`, `created_at`, `updated_at`. Индексы не комментируем.

`chatId`: `bigint` в MySQL, в PHP `int` (64-bit). Не `string`, пока нет требования к 32-bit runtime.

Telegram user id не добавляем: его не просили; при необходимости — отдельный change.

### 3. Значения enum

**Выбор:** string-backed enum:

- `Pending` → `pending` — не обработан
- `ProcessedSuccess` → `processed_success` — обработан успешно
- `ProcessedError` → `processed_error` — обработан с ошибкой

Хранение: Doctrine `enumType` на PHP enum (не отдельная MySQL ENUM-колонка со своим набором, чтобы миграции значений проще менять) **или** `string` column + PHP enum. **Выбор:** колонка `VARCHAR` + Doctrine `enumType` PHP enum — проще эволюционировать, чем native MySQL ENUM.

Альтернатива: integer-backed — хуже читается в SQL.

### 4. Инварианты статуса

Конструктор: `status = Pending`, `errorText = null`, `createdAt`/`updatedAt` = now.

Методы:

- `markProcessedSuccess(): void` — статус success, `errorText = null`
- `markProcessedError(string $errorText): void` — статус error, непустой текст ошибки

Пустой `errorText` при `ProcessedError` не допускается (доменное исключение от `CoreException`). Успех всегда сбрасывает текст ошибки.

Timestamps: `PrePersist` / `PreUpdate` на entity (разрешённое исключение Doctrine в Domain/Entity).

### 5. Репозиторий

`App\Infrastructure\Persistence\Repository\ProcessedTelegramMessageRepository` extends `ServiceEntityRepository`. Методы: `save`, `findOneByChatAndMessageId`. Без Application-порта в этом change: репозиторий нужен миграции/тестам и следующему use case; Presentation его не вызывает.

### 6. Тесты

Unit: конструктор (pending, пустая ошибка), `markProcessedSuccess`, `markProcessedError`, отказ пустой ошибки. `#[CoversClass]` / `#[CoversMethod]` по `docs/testing.md`. Functional на живую БД в этом change не обязателен.

## Risks / Trade-offs

- [Нет telegram user id] → нельзя надёжно связать записи одного человека при смене имени. Mitigation: добавить поле отдельным change, если понадобится.
- [Уникальность chat+message без update_id] → edited-сообщения с тем же message_id не создадут новую строку. Mitigation: обновлять ту же запись в будущем сценарии или хранить update_id отдельно.
- [Репозиторий без порта] → соблазн вызвать его из Presentation. Mitigation: следующий сценарий вводит порт; до тех пор репозиторий не используется из CLI/HTTP.

## Migration Plan

1. Добавить entity, enum, репозиторий, тесты.
2. Сгенерировать и проверить Doctrine-миграцию.
3. Откат: `doctrine:migrations:migrate prev` (DROP table). Данные обработанных сообщений после отката теряются — на этапе первой таблицы приемлемо.

## Open Questions

Нет: telegram user id и пайплайн обработки сознательно отложены и не влияют на spec этого change.
