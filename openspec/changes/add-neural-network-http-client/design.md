## Context

Слоистая раскладка и исходящий HTTP уже есть (`docs/architecture.md`, `docs/http-clients.md`): сценарии зависят от портов Application, клиенты живут в `Infrastructure/Transport/{Integration}` со scoped `HttpClientInterface` и отдельным `{INTEGRATION}_API_HOST`. Telegram-клиент — образец порта, enum URL, mapper’ов и исключений.

Мотивация — proposal.md. Требования — specs/neural-network-http-client/spec.md.

Локальный провайдер этого change — LM Studio / совместимый OpenAI HTTP на `http://127.0.0.1:1234` (native `/api/v1/*` и совместимые `/v1/*`).

## Goals / Non-Goals

**Goals:**

- Один порт Application со всеми операциями spec (native + compatible), типизированные DTO и коллекции.
- Первая реализация: локальный хост из env, пустой ключ = без Authorization.
- Расширение: новый провайдер = новый scoped-клиент + `{PROVIDER}_API_HOST` + `{PROVIDER}_API_KEY`, тот же порт.
- `ApiUrlEnum`, `final readonly` клиент, mapper’ы, исключения порта от `CoreException`, `@throws` на порте.
- Unit-тесты по `docs/testing.md`.

**Non-Goals:**

- SSE/streaming (`stream: true`).
- Use case бота, console-команда «спросить модель», выбор модели в UI.
- Второй конкретный провайдер (OpenAI/Anthropic cloud) в этом change.
- Functional-тесты против живого LM Studio.
- Общий HttpClient без `#[Target]`.

## Decisions

### 1. Порт в Application, клиент в Infrastructure

**Выбор:** `App\Application\Port\NeuralNetworkGateway` — полный контракт. Реализация `App\Infrastructure\Transport\NeuralNetwork\NeuralNetworkApiClient` с `#[AsAlias(NeuralNetworkGateway::class)]`. Presentation и use case не имеют права `use App\Infrastructure\...`.

Методы порта (имена фиксируем здесь, чтобы tasks не разъехались):

| Метод | HTTP |
| --- | --- |
| `listNativeModels()` | `GET /api/v1/models` |
| `nativeChat(NativeChatRequest)` | `POST /api/v1/chat` |
| `loadModel(string $modelId)` | `POST /api/v1/models/load` |
| `downloadModel(string $modelKey)` | `POST /api/v1/models/download` |
| `getDownloadStatus(string $jobId)` | `GET /api/v1/models/download/status/{job_id}` |
| `listModels()` | `GET /v1/models` |
| `createResponse(CreateResponseRequest)` | `POST /v1/responses` |
| `createChatCompletion(ChatCompletionRequest)` | `POST /v1/chat/completions` |
| `createCompletion(CompletionRequest)` | `POST /v1/completions` |
| `createEmbedding(EmbeddingRequest)` | `POST /v1/embeddings` |
| `createMessage(MessagesRequest)` | `POST /v1/messages` |

Списки возвращают `*Collection`, не `array`. Тела запросов — Application request DTO (не ассоциативные массивы на порте).

**Почему:** один контракт для сценариев; HTTP остаётся за портом.

**Альтернатива:** только Infrastructure-класс — ломает слои. Два порта (inference vs model management) — лишняя сложность при одном потребителе.

### 2. Расширение провайдеров: хост + ключ на scoped-клиент

**Выбор:** каждый провайдер — своя пара env и свой ключ в `scoped_clients` (`docs/http-clients.md`: один HOST на один сервис). Первый:

```
NEURAL_NETWORK_API_HOST=http://127.0.0.1:1234
NEURAL_NETWORK_API_KEY=
```

Scoped `neural_network` с `base_uri: '%env(resolve:NEURAL_NETWORK_API_HOST)%'`. Ключ **не** в yaml (`auth_bearer` на scoped сломает локальный режим без ключа).

Следующий провайдер (вне этого change): `OPENAI_API_HOST` + `OPENAI_API_KEY`, scoped `openai`, класс в `Transport/OpenAi` (или копия с другим Target), `implements NeuralNetworkGateway`. Операции, которых нет у провайдера (load/download), бросают `NeuralNetworkUnsupportedOperationException` (наследник `CoreException`, в `@throws` порта). Локальный клиент реализует все методы.

Если понадобятся несколько провайдеров одновременно — tagged implementations + locator в Application (`get(string $name): NeuralNetworkGateway`). В этом change locator не вводим: один `AsAlias` на локальный клиент.

**Почему:** «добавить хост и ключ» совпадает с существующим правилом интеграций; пустой ключ — валидная конфигурация, не ошибка.

**Альтернатива:** один универсальный клиент с host/key в рантайме без scoped `base_uri` — запрещено docs (абсолютный URL / голый HttpClient). Один yaml-клиент на все нейросети — запрещено «один HOST на два сервиса».

### 3. Credentials DTO и Bearer

**Выбор:** `Infrastructure/Transport/NeuralNetwork/Config/ApiCredentialsDto`:

- `#[Autowire('%env(NEURAL_NETWORK_API_HOST)%')] string $host`
- `#[Autowire('%env(NEURAL_NETWORK_API_KEY)%')] string $apiKey`

Пустой host → `NeuralNetworkConfigurationException` до HTTP. Пустой key → запросы без `auth_bearer`. Непустой key → `'auth_bearer' => $apiKey` на каждом `request()`.

**Альтернатива:** yaml `auth_bearer: '%env(NEURAL_NETWORK_API_KEY)%'` — отправит пустой/битый заголовок на localhost.

### 4. Timeout scoped-клиента

**Выбор:** у `neural_network` `timeout: 1800` (30 минут: локальный инференс дольше 3s default). Retry как в default yaml (POST на 500 не ретраить).

**Альтернатива:** timeout в каждом `request()` — обход yaml без нужды.

### 5. Enum URL и не-стриминг

**Выбор:** `ApiUrlEnum` (`METHOD:/relative/path`):

- `GET:/api/v1/models`
- `POST:/api/v1/chat`
- `POST:/api/v1/models/load`
- `POST:/api/v1/models/download`
- `GET:/api/v1/models/download/status/{job_id}` (`vars`: `job_id`)
- `GET:/v1/models`
- `POST:/v1/responses`
- `POST:/v1/chat/completions`
- `POST:/v1/completions`
- `POST:/v1/embeddings`
- `POST:/v1/messages`

Тела JSON через serializer `normalize` + `'json' =>`. Для compatible POST явно `stream: false` в теле, если поле есть в DTO, чтобы сервер не ушёл в SSE.

**Альтернатива:** абсолютные URL в PHP — запрещено.

### 6. Ошибки

**Выбор:** `App\Domain\Exception\CoreException` уже есть. Порт:

- `NeuralNetworkConfigurationException` — пустой host
- `NeuralNetworkValidationException` — blank model/input и т.п.
- `NeuralNetworkTransportException` — HTTP/JSON/`ok`/`error` провайдера
- `NeuralNetworkUnsupportedOperationException` — для будущих провайдеров; локальный клиент не бросает при штатных методах

На методах порта — `@throws` этих типов. Реализация: `catch (CoreException) { throw $e; } catch (Throwable) { wrap transport with previous }`. HTTP ≥400 или тело с `error` — transport.

**Альтернатива:** исключения Infrastructure на порте — запрещено `docs/exceptions.md`. Пустой ключ как configuration error — отклонено: локальный режим без auth.

### 7. Mapper’ы и DTO

**Выбор:** ответы мапит `Infrastructure/Transport/NeuralNetwork/Mapper/*Mapper` (`map`). Вложенные объекты — отдельные mapper’ы. Конструктор mapper’а — только другие mapper’ы.

Минимальные поля Application DTO (остальное провайдера не тащить, пока нет сценария):

- модель: `id` (+ `object`/`ownedBy`, если пришло)
- chat/completion/messages/response: идентификатор ответа при наличии + текст ассистента
- embedding: вектор как коллекция float (обёртка `EmbeddingVector` + `EmbeddingVectorCollection`)
- download: `jobId` + `status`

Request DTO валидирует клиент до HTTP (blank strings / empty message lists / maxTokens ≤ 0).

**Альтернатива:** маппинг в клиенте — запрещено `docs/mappers.md`.

### 8. Логи

**Выбор:** перед запросом `LoggerService::info` с case enum и безопасными опциями (без ключа). На ошибке — `logException`. Как у правил `docs/http-clients.md` / `docs/logging.md`.

### 9. Тесты

**Выбор:** PHPUnit + `MockHttpClient` / `MockResponse`.

- клиент: `tests/Unit/Infrastructure/Transport/NeuralNetwork/NeuralNetworkApiClientTest.php` — `CoversClass` + `CoversMethod` на каждый метод порта: happy path, validation (без HTTP), пустой host, HTTP failure, empty key (нет Authorization), non-empty key (Bearer), empty list models
- каждый mapper: `tests/Unit/Infrastructure/Transport/NeuralNetwork/Mapper/{Name}MapperTest.php`

Snapshot / Functional kernel не для этого клиента.

## Risks / Trade-offs

- [Локальный хост в committed `.env`] → это не секрет; ключ остаётся пустым. Стенд без LM Studio получит transport error при вызове порта — ожидаемо, порта не дергать без сервиса.
- [Timeout 30 минут] → долгий запрос держит PHP-воркер; стриминг и очереди — следующие change.
- [Один AsAlias] → два провайдера сразу потребуют locator; закладываем исключения unsupported и правило «новый scoped + env».
- [Урезанные DTO] → редкие поля OpenAI не на порте; расширять DTO по сценариям.

## Migration Plan

1. Env хоста/ключа + scoped `neural_network` с timeout 1800 (30 минут).
2. Порт, request/response DTO, исключения.
3. Credentials, `ApiUrlEnum`, клиент, mapper’ы.
4. Unit-тесты; phpstan `src/` + `tests/`.

Откат: удалить scoped-клиент, env, каталог Transport/NeuralNetwork и порт/DTO/исключения.

## Open Questions

Нет.
