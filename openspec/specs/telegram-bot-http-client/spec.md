# telegram-bot-http-client Specification

## Purpose

Даёт приложению транспорт к Telegram Bot API: забрать входящие сообщения и отправить текстовый ответ, не встраивая HTTP и секрет токена в сценарии.

## Requirements

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

### Requirement: Bot API host from environment

The Bot API origin MUST be read from the environment variable `TELEGRAM_API_HOST`. Requests MUST use a scoped Symfony HttpClient bound to that origin and MUST call relative paths (not an absolute host hardcoded in PHP). The committed env templates MUST declare `TELEGRAM_API_HOST` without a live stand URL. Host and token MUST be separate variables.

#### Scenario: Host is configured via env

- **WHEN** `TELEGRAM_API_HOST` is set to a Bot API origin
- **THEN** retrieve and send operations call that origin with relative Bot API paths

#### Scenario: Committed files have no live host

- **WHEN** a developer inspects committed `.env` / env examples
- **THEN** `TELEGRAM_API_HOST` is present as an empty or placeholder value

#### Scenario: Paths are relative to the scoped client

- **WHEN** the client retrieves or sends a message
- **THEN** the HTTP request URI is relative to `TELEGRAM_API_HOST` and does not embed a hardcoded `https://api.telegram.org` in PHP

### Requirement: Bot API errors surface to the caller

When Telegram responds with a transport failure or with `ok` equal to false, the system MUST fail the operation with an error that includes the API description when present. Partial or silent success MUST NOT be reported. Failures that cross the Application port MUST be types that extend `CoreException` and MUST be declared on the port. HttpClient, JSON, and `\Error` MUST NOT leak through the port.

#### Scenario: API returns ok false

- **WHEN** Telegram responds with HTTP success and JSON where `ok` is false
- **THEN** the operation fails and the error includes the API description if Telegram provided one

#### Scenario: HTTP transport failure

- **WHEN** the HTTP request to Telegram cannot complete (timeout, connection error, or non-success HTTP status)
- **THEN** the operation fails and does not return a message collection or sent message

#### Scenario: Unexpected errors are wrapped

- **WHEN** retrieve or send hits an unexpected throwable (including HTTP client or JSON failures)
- **THEN** the caller receives a port exception that extends `CoreException`, with the original throwable as `previous`

### Requirement: External payloads are mapped by Mapper types

Conversion from Telegram JSON into Application DTOs MUST be done by classes named with postfix `Mapper` and a method `map`, living under the Telegram transport. Nested objects MUST use a dedicated mapper rather than copying fields in several places. Mapper constructors MUST NOT depend on HttpClient, env, logger, or cache.

#### Scenario: Client does not inline nested mapping

- **WHEN** getUpdates or sendMessage succeeds with a JSON payload
- **THEN** Application DTOs are produced via `Mapper::map`, not by assembling nested fields inside the HTTP client

### Requirement: Tests follow project test conventions

Automated tests MUST follow `docs/testing.md`. They MUST live under `tests/Unit/` or `tests/Functional/` with a path after that root matching the production class under `src/`. The test class MUST be named `{ClassName}Test`. Each test class MUST declare coverage with `CoversClass` and `CoversMethod` for every production method it exercises. This change MUST add unit tests for the Bot API HTTP client and for each new mapper, and MUST NOT place those tests in `tests/Functional/` unless the test boots the application kernel. A functional test of an HTTP endpoint MUST compare the response body to a JSON snapshot via `JsonPrettyMatchesSnapshots` (this change does not add such an endpoint test).

#### Scenario: Unit test path matches the HTTP client

- **WHEN** a unit test covers the Telegram Bot HTTP client class
- **THEN** the file is `tests/Unit/Infrastructure/Transport/Telegram/{ClassName}Test.php` where `{ClassName}` is the production class name

#### Scenario: Coverage names the class and methods

- **WHEN** a developer inspects the Bot API HTTP client unit test
- **THEN** the test class declares coverage of that production class and of `getMessages` and `sendMessage`

#### Scenario: Mapper tests mirror mapper classes

- **WHEN** a unit test covers a Telegram transport mapper
- **THEN** the file is `tests/Unit/Infrastructure/Transport/Telegram/Mapper/{ClassName}Test.php` and declares `CoversClass` / `CoversMethod` for `map`

#### Scenario: Tests are not dumped at tests root

- **WHEN** a developer inspects `tests/` for this capability
- **THEN** test files are not placed directly in `tests/` or in a path that omits `Unit`/`Functional` or the Infrastructure/Transport/Telegram segments
