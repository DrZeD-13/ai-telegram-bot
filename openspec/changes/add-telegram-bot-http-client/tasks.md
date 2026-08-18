## 1. Dependencies and env

- [x] 1.1 Require `symfony/http-client` (Symfony 7.4) via Composer/Flex; keep generated http-client config if Flex adds it
- [x] 1.2 Add empty `TELEGRAM_BOT_TOKEN=` to committed `.env` and `.docker.loc/.env.example`; do not commit a live token
- [x] 1.3 Add `phpunit/phpunit` as a dev dependency so MockHttpClient tests can run
- [x] 1.4 Add empty `TELEGRAM_API_HOST=` (origin only, e.g. later `https://api.telegram.org`) to `.env` and `.docker.loc/.env.example`; do not hardcode the host in PHP (`docs/http-clients.md`)
- [x] 1.5 Add `config/packages/http_clients.yaml`: `default_options` (JSON headers, timeout, `retry_failed`) and scoped client `telegram` with `base_uri: '%env(resolve:TELEGRAM_API_HOST)%'`

## 2. Application port, DTO, exceptions

- [x] 2.1 Add `App\Application\Port\TelegramBotGateway` with `getMessages(?int $offset = null): IncomingTelegramMessageCollection` and `sendMessage(int|string $chatId, string $text): SentTelegramMessage`
- [x] 2.2 Add Application DTO `IncomingTelegramMessage` (updateId, chatId, messageId, text) and `IncomingTelegramMessageCollection`; `getMessages` returns this collection as the method result
- [x] 2.3 Add Application DTO `SentTelegramMessage` (chatId, messageId, text)
- [x] 2.4 Add `App\Domain\Exception\CoreException`; make Application port exceptions (`TelegramBotConfigurationException`, `TelegramBotValidationException`, `TelegramBotTransportException`) extend `CoreException` (`docs/exceptions.md`)
- [x] 2.5 Add `@throws` on `TelegramBotGateway` methods listing only those Application/domain types; do not declare Infrastructure or HttpClient exceptions on the port

## 3. HTTP client (`docs/http-clients.md`)

- [x] 3.1 Make `TelegramBotHttpClient` `final readonly`, `#[AsAlias(TelegramBotGateway::class)]`; inject `#[Target('telegram')] HttpClientInterface` (not the default client) and `#[Autowire('%env(TELEGRAM_BOT_TOKEN)%')] string $botToken`
- [x] 3.2 Add `ApiUrlEnum` for `getUpdates` and `sendMessage` (`METHOD:/relative/path`); call relative URIs against scoped `base_uri`; put the token only in the Bot API path (`/bot{token}/…`) via `vars`, never as a second host env
- [x] 3.3 Keep behavior: `getUpdates` with timeout 0 and optional offset (`query` + `array_filter`); include only updates that contain `message`; `sendMessage` rejects blank text before HTTP and sends `chat_id`/`text` as `json`
- [x] 3.4 On the port implementation: rethrow `CoreException`; catch `Throwable` (including `\Error`) and wrap into a type listed on the port with a clear message and `previous` — never leak HttpClient/JSON exceptions (`docs/exceptions.md`)
- [x] 3.5 Empty `TELEGRAM_BOT_TOKEN` (and empty `TELEGRAM_API_HOST` if the client can observe it): throw the port configuration exception before any HTTP call

## 4. Mappers (`docs/mappers.md`)

- [x] 4.1 Add Transport mappers (`Infrastructure/Transport/Telegram/Mapper/`) with postfix `Mapper` and method `map` (incoming update/message → `IncomingTelegramMessage`, sendMessage `result` → `SentTelegramMessage`); nested objects get a dedicated mapper, no field copy-paste
- [x] 4.2 Mapper constructors may depend only on other mappers (no HttpClient, env, logger, cache)
- [x] 4.3 Client uses mappers instead of inlined array-to-DTO mapping

## 5. Tests (`docs/testing.md`)

- [x] 5.1 PHPUnit suites `Unit` (`tests/Unit`) and `Functional` (`tests/Functional`); `requireCoverageMetadata="true"`
- [x] 5.2 Unit test for the HTTP client at `tests/Unit/Infrastructure/Transport/Telegram/TelegramBotHttpClientTest.php` with `CoversClass` / `CoversMethod` for `getMessages` and `sendMessage`: text messages, skip non-message, empty collection, send success, `ok: false`, HTTP failure, empty token, blank text (no request); MockHttpClient only, no snapshots
- [x] 5.3 Unit tests for each new mapper at `tests/Unit/Infrastructure/Transport/Telegram/Mapper/{Name}MapperTest.php` with `CoversClass` / `CoversMethod` for `map`
- [x] 5.4 Update the client unit test after `Target` / enum / wrap-`Throwable` / mapper extraction; keep path mirroring `src/`; add `CoversMethod` only for methods the test exercises
- [x] 5.5 Run the Unit suite and PHPStan (`src/` + `tests/`) at level max
