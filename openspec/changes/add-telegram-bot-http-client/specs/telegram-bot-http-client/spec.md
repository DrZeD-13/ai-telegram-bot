## Purpose

Даёт приложению транспорт к Telegram Bot API: забрать входящие сообщения и отправить текстовый ответ, не встраивая HTTP и секрет токена в сценарии.

## ADDED Requirements

### Requirement: Retrieve incoming messages

The system SHALL fetch pending incoming updates from Telegram Bot API. The retrieve operation MUST return a typed collection of received messages as its result (not a raw JSON array, not a list of raw updates, and not a wrapper DTO around the collection). Each item MUST include at least chat identifier, message identifier, and text when the update contains a text message. Updates without a message MUST be omitted from the collection. The caller MUST be able to pass an optional offset so already processed updates are not returned again.

#### Scenario: Incoming text messages are returned

- **WHEN** the Bot API has pending updates that contain text messages
- **THEN** the system returns a collection of those messages with chat id, message id, and text

#### Scenario: Non-message updates are skipped

- **WHEN** pending updates include callback queries or other non-message payloads and no message field
- **THEN** the returned collection does not include those updates

#### Scenario: Offset skips already seen updates

- **WHEN** the caller requests messages with an offset equal to the last processed update id plus one
- **THEN** the system does not return updates with a lower or equal update id

#### Scenario: Empty inbox

- **WHEN** there are no pending updates
- **THEN** the system returns an empty collection and does not treat this as an error

### Requirement: Send a text message

The system SHALL send a text message to a Telegram chat identified by chat id. On success it MUST return the sent message as a typed object with chat id, message id, and text. The request MUST use the bot token from configuration, not a token supplied by the caller on each send.

#### Scenario: Successful send

- **WHEN** the caller sends a non-empty text to a valid chat id
- **THEN** Telegram receives the message and the system returns the sent message with matching chat id and text

#### Scenario: Empty text is rejected

- **WHEN** the caller attempts to send an empty or whitespace-only text
- **THEN** the system does not call Telegram and reports a validation failure

### Requirement: Bot token from environment

The bot API token MUST be read from the environment variable `TELEGRAM_BOT_TOKEN`. The committed env templates MUST declare the variable without a real secret. The token MUST NOT appear in source code.

#### Scenario: Token is configured via env

- **WHEN** `TELEGRAM_BOT_TOKEN` is set to a valid bot token
- **THEN** retrieve and send operations authenticate against Bot API with that token

#### Scenario: Committed files have no secret

- **WHEN** a developer inspects committed `.env` / env examples
- **THEN** `TELEGRAM_BOT_TOKEN` is present as an empty or placeholder value and does not contain a live token

#### Scenario: Missing token fails closed

- **WHEN** retrieve or send is invoked and `TELEGRAM_BOT_TOKEN` is empty or unset
- **THEN** the system does not call Telegram with an empty credential and reports a configuration error

### Requirement: Bot API errors surface to the caller

When Telegram responds with a transport failure or with `ok` equal to false, the system MUST fail the operation with an error that includes the API description when present. Partial or silent success MUST NOT be reported.

#### Scenario: API returns ok false

- **WHEN** Telegram responds with HTTP success and JSON where `ok` is false
- **THEN** the operation fails and the error includes the API description if Telegram provided one

#### Scenario: HTTP transport failure

- **WHEN** the HTTP request to Telegram cannot complete (timeout, connection error, or non-success HTTP status)
- **THEN** the operation fails and does not return a message collection or sent message

### Requirement: Tests mirror production class paths

Automated tests MUST live under `tests/Unit/` or `tests/Functional/`. The directory path after that root MUST match the production class path under `src/`. The test class MUST be named `{ClassName}Test`. Unit tests of the Bot API HTTP client MUST use this layout. This change MUST add unit tests for the client; it MUST NOT place those tests in `tests/Functional/` unless the test boots the application kernel.

#### Scenario: Unit test path matches the HTTP client

- **WHEN** a unit test covers the Telegram Bot HTTP client class
- **THEN** the file is `tests/Unit/Infrastructure/Transport/Telegram/{ClassName}Test.php` where `{ClassName}` is the production class name

#### Scenario: Tests are not dumped at tests root

- **WHEN** a developer inspects `tests/` for this capability
- **THEN** test files are not placed directly in `tests/` or in a path that omits `Unit`/`Functional` or the Infrastructure/Transport/Telegram segments
