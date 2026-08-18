# Tests

Правила слоёв приложения — в [architecture.md](architecture.md). Исключения — в [exceptions.md](exceptions.md). Здесь — где лежат тесты, что обязано быть в каждом тесте и как писать HTTP-проверки со snapshot.

## Раскладка `tests/`

Тесты живут в корне репозитория, не в `src/`.

| Каталог | Назначение |
|---------|------------|
| `tests/Unit/` | Изолированные тесты класса: моки HTTP/БД, без ядра Symfony |
| `tests/Functional/` | Ядро, контейнер, HTTP приложения (`WebTestCase`) |
| `tests/System/` | Общая инфраструктура тестов (Driver, Trait), не test suite |

Путь **внутри** `Unit/` или `Functional/` совпадает с путём покрываемого класса относительно `src/`. Файл — `{ClassName}Test.php`. Namespace: PSR-4 `App\Tests\` → `tests/`.

`App\Infrastructure\Transport\Telegram\TelegramBotHttpClient` (`src/Infrastructure/Transport/Telegram/TelegramBotHttpClient.php`):

- unit: `tests/Unit/Infrastructure/Transport/Telegram/TelegramBotHttpClientTest.php`
- functional (если нужен): `tests/Functional/Infrastructure/Transport/Telegram/TelegramBotHttpClientTest.php`

`src/Kernel.php` → `tests/Unit/KernelTest.php`. Не класть тесты в корень `tests/`, не выкидывать сегменты пути.

```
tests/
  Unit/
  Functional/
  System/
    Driver/
    Trait/
```

PHPUnit suites: `Unit` → `tests/Unit`, `Functional` → `tests/Functional`. `tests/System` в suite не входит.

## Обязательный coverage

Каждый тестовый класс **обязан** явно сказать, какой production-класс и какие методы он покрывает. Без этого PHPUnit падает (`requireCoverageMetadata`).

- Класс: `#[CoversClass(Foo::class)]`
- Каждый проверяемый метод: `#[CoversMethod(Foo::class, 'bar')]`
- Несколько методов — несколько атрибутов `CoversMethod`

Нельзя писать тест «вообще про модуль» без указания класса и методов. `#[CoversNothing]` — только если тест сознательно ничего в `src/` не покрывает (редко; нужно обоснование в комментарии).

## HTTP-тесты и snapshot

Functional-тест, который ходит в HTTP приложения (контроллер, JSON API), **обязан** сверять тело ответа со snapshot, а не руками собирать ожидаемый JSON.

- Trait: `App\Tests\System\Trait\JsonPrettyMatchesSnapshots`
- Вызов: `$this->assertMatchesSnapshotJsonPretty($client->getResponse()->getContent())`
- Драйвер: `App\Tests\System\Driver\JsonPrettyDriver` (pretty JSON, unicode не экранируется)
- Файлы: `__snapshots__/` рядом с тестом, имя вида `{TestClass}__{testMethod}__1.json`

Unit-тест исходящего HTTP-клиента (Telegram Bot API через `MockHttpClient`) snapshot не обязан: там проверяются DTO и исключения, а не JSON-контракт нашего HTTP API.

### Завести или обновить snapshot

Первый прогон без файла snapshot падает и подсказывает создать эталон. Создать/перезаписать:

```bash
UPDATE_SNAPSHOTS=true vendor/bin/phpunit --filter testPing
```

Коммитить `__snapshots__/*.json` вместе с тестом. Не править snapshot руками, если можно перегенерировать тем же тестом.

## Скелет: unit

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\Telegram;

use App\Infrastructure\Transport\Telegram\TelegramBotHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(TelegramBotHttpClient::class)]
#[CoversMethod(TelegramBotHttpClient::class, 'getMessages')]
#[CoversMethod(TelegramBotHttpClient::class, 'sendMessage')]
final class TelegramBotHttpClientTest extends TestCase
{
    public function testGetMessagesReturnsTextMessages(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode(['ok' => true, 'result' => []], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            ),
        ]);

        $client = new TelegramBotHttpClient($httpClient, 'bot-token');
        $messages = $client->getMessages();

        self::assertCount(0, $messages);
    }
}
```

## Скелет: functional HTTP + snapshot

Нужны `symfony/browser-kit` (и обычно `symfony/css-selector`) в require-dev, если ещё нет.

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\Presentation\Http\Controller;

use App\Presentation\Http\Controller\PingController;
use App\Tests\System\Trait\JsonPrettyMatchesSnapshots;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(PingController::class)]
#[CoversMethod(PingController::class, '__invoke')]
final class PingControllerTest extends WebTestCase
{
    use JsonPrettyMatchesSnapshots;

    public function testPing(): void
    {
        $client = self::createClient();

        $client->request(
            method: Request::METHOD_GET,
            uri: '/ping',
        );

        self::assertResponseIsSuccessful();
        $this->assertMatchesSnapshotJsonPretty($client->getResponse()->getContent());
    }
}
```

После первого успешного прогона с `UPDATE_SNAPSHOTS=true` появится файл:

```
tests/Functional/Presentation/Http/Controller/__snapshots__/PingControllerTest__testPing__1.json
```

Фикстуры БД в functional — `App\Tests\System\Trait\FixturesTrait` (`loadFixtures([...])` после `createClient()` / `bootKernel()`).