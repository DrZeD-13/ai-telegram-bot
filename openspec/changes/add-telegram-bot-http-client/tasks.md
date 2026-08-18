## 1. Dependencies and env

- [ ] 1.1 Require `symfony/http-client` (Symfony 7.4) via Composer/Flex; keep generated http-client config if Flex adds it
- [ ] 1.2 Add empty `TELEGRAM_BOT_TOKEN=` to committed `.env` and `.docker.loc/.env.example`; do not commit a live token
- [ ] 1.3 Add `phpunit/phpunit` or `symfony/phpunit-bridge` as a dev dependency so MockHttpClient tests can run

## 2. Application port and DTO

- [ ] 2.1 Add `App\Application\Port\TelegramBotGateway` with `getMessages(?int $offset = null): IncomingTelegramMessageCollection` and `sendMessage(int|string $chatId, string $text): SentTelegramMessage`
- [ ] 2.2 Add Application DTO `IncomingTelegramMessage` (updateId, chatId, messageId, text) and `IncomingTelegramMessageCollection` next to it; `getMessages` returns this collection as the method result
- [ ] 2.3 Add Application DTO `SentTelegramMessage` (chatId, messageId, text)
- [ ] 2.4 Add Application exceptions: configuration (empty token), validation (empty text), transport (API/HTTP failure including description)

## 3. HTTP client

- [ ] 3.1 Implement `App\Infrastructure\Transport\Telegram\TelegramBotHttpClient` with `HttpClientInterface` and `#[Autowire('%env(TELEGRAM_BOT_TOKEN)%')] string $botToken`; mark the class `#[AsAlias(TelegramBotGateway::class)]`
- [ ] 3.2 Map `getUpdates` (`timeout` 0, optional `offset`) onto `IncomingTelegramMessageCollection` and return that collection: keep updates with `message`, skip others; include `update_id`
- [ ] 3.3 Implement `sendMessage` to Bot API; reject blank text before HTTP; map `result` to `SentTelegramMessage`
- [ ] 3.4 Fail closed on empty token (no HTTP); wrap `ok: false` and HTTP/transport errors into the Application transport exception

## 4. Tests and verify

- [ ] 4.1 Configure PHPUnit with suites `Unit` (`tests/Unit`) and `Functional` (`tests/Functional`)
- [ ] 4.2 Add `tests/Unit/Infrastructure/Transport/Telegram/TelegramBotHttpClientTest.php` (`App\Tests\Unit\...`) with `MockHttpClient`/`MockResponse`: text messages, skip non-message updates, empty collection, send success, `ok: false`, HTTP failure, empty token, blank text (no request)
- [ ] 4.3 Run the Unit suite and PHPStan on `src/` so new types pass level max
