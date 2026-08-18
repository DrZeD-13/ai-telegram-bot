# HTTP-клиенты внешних сервисов

Правила слоёв — в [architecture.md](architecture.md). Исключения — в [exceptions.md](exceptions.md). Преобразование DTO — в [mappers.md](mappers.md). Тесты клиентов — в [testing.md](testing.md). Логи — в [logging.md](logging.md).

Новый исходящий HTTP-клиент — это интеграция в `Infrastructure`, не сценарий Application и не контроллер. Хост, таймауты и retry задаются конфигурацией Symfony HttpClient, не строками URL в PHP.

## Размещение

Каталог по системе внутри транспорта:

```
src/Infrastructure/Transport/{Integration}/
  {Integration}ApiClient.php
  ApiUrlEnum.php
  Dto/          # только внутренний маппинг ответа внешнего API
  Config/       # credentials DTO, если нужны
```

Имя `{Integration}` — имя сервиса (`Telegram`, `OtherApi`), не общее `Http` на все API.

Потребители (Application, Presentation) зависят от **порта Application**, не от класса клиента:

```
src/Application/Port/{Integration}Gateway.php
```

Клиент реализует этот порт (`#[AsAlias]`) и отдаёт Application DTO / коллекции (не `ResponseInterface`, не Infrastructure DTO).

## Env: отдельный хост на сервис

На каждый внешний сервис — **своя** переменная хоста. Не хардкодить `https://…` в клиенте и не переиспользовать хост другой интеграции.

Имя: `{INTEGRATION}_API_HOST` (или `{INTEGRATION}_HOST`), значение — origin без path метода (`https://api.example.com`).

```dotenv
YOUR_INTEGRATION_API_HOST=
OTHER_API_HOST=
```

В yaml только `%env(resolve:YOUR_INTEGRATION_API_HOST)%` (`resolve` — чтобы в значении мог быть другой env). Пустой placeholder в закоммиченных `.env` / `.docker.loc/.env.example`. Секреты и реальные URL стендов — в `.env.local` / оркестраторе.

Логин, пароль, токен — отдельные env, не в том же ключе, что хост. Примеры: `YOUR_INTEGRATION_USERNAME`, `YOUR_INTEGRATION_PASSWORD`, `TELEGRAM_BOT_TOKEN`.

## Конфигурация: `config/packages/http_clients.yaml`

Один файл на все исходящие клиенты. Не дублировать `base_uri` в `services.yaml`.

```yaml
framework:
  http_client:
    max_host_connections: 10
    default_options:
      headers:
        Accept: 'application/json'
        'Content-Type': 'application/json'
        'Cache-Control': 'no-cache'
      timeout: 3
      retry_failed:
        http_codes:
          0: [ 'GET', 'HEAD' ]   # retry network errors if request method is GET or HEAD
          429: true              # retry all responses with 429 status code
          500: [ 'GET', 'HEAD' ]
        max_retries: 2
        multiplier: 3
        max_delay: 2000
        jitter: 0.3
    scoped_clients:
      your_integration:
        base_uri: '%env(resolve:YOUR_INTEGRATION_API_HOST)%'
      other_integration:
        base_uri: '%env(resolve:OTHER_API_HOST)%'
        auth_basic: '%app.username%:%app.password%'
        timeout: 10
```

Правила scoped-клиента:

- Ключ (`your_integration`) = имя для `#[Target]`. `snake_case`.
- `base_uri` обязателен. В PHP уходят **относительные** path (`/api/v1/items`, `GET:/api/{id}`).
- Общие JSON-заголовки, timeout и retry — в `default_options`.
- Отклонения только у конкретного scoped: другой `timeout`, `auth_basic`, свои headers.
- Retry: сеть (`0`) и `500` — для `GET`/`HEAD`; `429` — для всех методов. Не ретраить неидемпотентный `POST` на 500 по умолчанию.
- Basic-auth, который не меняется от запроса к запросу, — в yaml scoped-клиента, не в каждом `request()`.

## Скелет клиента

`final readonly class`. HttpClient **только** через `#[Target]` с именем scoped-клиента. Без Target при нескольких интеграциях контейнер не отличит клиенты.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\YourIntegration;

use App\Application\Port\YourIntegrationGateway;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(YourIntegrationGateway::class)]
final readonly class YourIntegrationApiClient implements YourIntegrationGateway
{
    public function __construct(
        #[Target('your_integration')]
        private HttpClientInterface $httpClient,
    ) {
    }
}
```

Низкоуровневый transport, если методов много и нужен общий `request()`:

```php
final readonly class YourIntegrationApiClient implements YourIntegrationApiClientInterface
{
    private const string TOKEN_CACHE_KEY = 'auth-token';
    private const int TOKEN_CACHE_LIFETIME = 60 * 60; // 1 час

    public function __construct(
        #[Target('your_integration')] private HttpClientInterface $httpClient,
        // serializer, cache, logger, credentials — по необходимости
    ) {
    }
}
```

Интерфейс с сырым `request(): ResponseInterface` остаётся **внутри** `Infrastructure`. Наружу (Application) смотрят типизированные методы порта.

## URL и метод: enum

Не размазывать строки path по методам клиента.

```php
enum ApiUrlEnum: string
{
    case ListItems = 'GET:/api/v1/items';
    case GetItem = 'GET:/api/v1/items/{id}';
    case CreateItem = 'POST:/api/v1/items';

    public function method(): string
    {
        return explode(':', $this->value, 2)[0];
    }

    public function uri(): string
    {
        return explode(':', $this->value, 2)[1];
    }
}
```

Плейсхолдеры `{id}` заполнять через опцию HttpClient `vars`, query — через `query`. Пустые query отфильтровывать (`array_filter`), чтобы не слать `?limit=`.

```php
$response = $this->httpClient->request(
    $url->method(),
    $url->uri(),
    [
        'vars' => $pathVariables,
        'query' => array_filter($queryParameters),
        'json' => $body, // объект нормализовать в array, не вручную клеить JSON
    ],
);
```

## Ошибки

Через порт Application — только исключения из `@throws` интерфейса (доменные или Application-исключения порта, все наследуют `CoreException`). `\Error`, HTTP-клиент, JSON — поймать и обернуть. Внутренние типы интеграции не должны пересекать границу порта. Подробности и скелет `try/catch` — в [exceptions.md](exceptions.md).

Пустой хост/токен проверять **до** запроса; бросать тип, объявленный на порте, не ходить в сеть с пустым credential.

## Авторизация

Два нормальных варианта:

1. **Static basic** — `auth_basic` на scoped-клиенте в yaml; логин/пароль из env → parameters (`%app.your_client.username%`).
2. **Bearer + login** — credentials DTO с `#[Autowire('%env(...)%')]` или parameters; логин отдельным URL из enum; токен в `CacheInterface` с TTL; в рабочие запросы — `auth_bearer`. Ключ кэша уникален для интеграции (`auth-token`).

Не класть токен в path URL (исключение — API вроде Telegram `bot<token>/method`, если так требует протокол). Не логировать `auth_bearer`, пароли и полный URL с секретом.

Credentials не размазывать по клиенту — отдельный `Config/ApiCredentialsDto`.

## Сериализация

Тело запроса: `normalize` DTO в array + `'json' => …` (null-поля лучше `SKIP_NULL_VALUES`). Ответ внешнего API, если контракт ≠ Application DTO, маппить через `*Mapper` ([mappers.md](mappers.md)) — не в клиенте смешивать HTTP и ручную сборку вложенных объектов.

Если API в `snake_case`, а PHP в camelCase — named serializer (`config/packages/serializer.yaml`) и тот же `#[Target('your_integration')]` на `SerializerInterface`, что и у HttpClient.

Коллекции на границе Application — по [architecture.md](architecture.md) (`FooCollection`, не голый `array`).

## Логи

Перед запросом — `info` с URL enum и безопасными опциями (без токена). На ошибке — `logException` / `error` с контекстом исключения. Не логировать сырой response с ПДн без нужды. Как инжектить логгер и какие уровни выбирать — [logging.md](logging.md).

## Чеклист новой интеграции

1. Env хоста (+ credentials) в `.env` (пусто) и `.docker.loc/.env.example`.
2. Scoped-клиент в `http_clients.yaml`.
3. Порт Application + DTO/коллекции; исключения порта — по [exceptions.md](exceptions.md).
4. `ApiUrlEnum`, клиент с `#[Target]`, `final readonly`.
5. Unit-тест: путь как у класса, `CoversClass` / `CoversMethod`, `MockHttpClient` ([testing.md](testing.md)).

## Не делать

- Инжектить «голый» `HttpClientInterface` без `#[Target]`, если в проекте уже есть scoped-клиенты.
- Собирать абсолютный URL в PHP при заданном `base_uri`.
- Один scoped-клиент / один HOST на два разных сервиса.
- Возвращать из порта `ResponseInterface` или JSON-array.
- Конфигурировать timeout/retry только в коде `request()` в обход yaml (точечный override — только осознанно).
