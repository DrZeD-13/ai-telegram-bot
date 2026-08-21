## 1. Env and scoped HTTP client

- [x] 1.1 Add `NEURAL_NETWORK_API_HOST=http://127.0.0.1:1234` and empty `NEURAL_NETWORK_API_KEY=` to committed `.env` and `.docker.loc/.env.example`; do not commit a live key; do not hardcode the host in PHP
- [x] 1.2 Add scoped client `neural_network` in `config/packages/http_clients.yaml` with `base_uri: '%env(resolve:NEURAL_NETWORK_API_HOST)%'` and `timeout: 1800` (30 minutes); do not set `auth_bearer` in yaml

## 2. Application port, DTO, exceptions

- [x] 2.1 Add `App\Application\Port\NeuralNetworkGateway` with methods from design.md (`listNativeModels`, `nativeChat`, `loadModel`, `downloadModel`, `getDownloadStatus`, `listModels`, `createResponse`, `createChatCompletion`, `createCompletion`, `createEmbedding`, `createMessage`)
- [x] 2.2 Add Application request DTO: `NativeChatRequest`, `CreateResponseRequest`, `ChatCompletionRequest`, `CompletionRequest`, `EmbeddingRequest`, `MessagesRequest` (typed messages where needed; no raw `array` on the port)
- [x] 2.3 Add Application result DTO and collections: model + `NeuralNetworkModelCollection`; native chat result; load result; download job; download status; response result; chat/text completion results; `EmbeddingVector` + `EmbeddingVectorCollection`; messages result
- [x] 2.4 Add port exceptions extending `CoreException`: `NeuralNetworkConfigurationException`, `NeuralNetworkValidationException`, `NeuralNetworkTransportException`, `NeuralNetworkUnsupportedOperationException`
- [x] 2.5 Add `@throws` on every `NeuralNetworkGateway` method listing only those Application types (plus unsupported where a future provider may not implement the operation); do not declare HttpClient exceptions on the port

## 3. HTTP client (`docs/http-clients.md`)

- [x] 3.1 Add `Config/ApiCredentialsDto` with host and key from env; empty host throws configuration exception before HTTP; empty key is allowed
- [x] 3.2 Add `ApiUrlEnum` for all eleven relative paths from design.md (`METHOD:/path`, `{job_id}` via `vars`)
- [x] 3.3 Add `final readonly` `NeuralNetworkApiClient` with `#[AsAlias(NeuralNetworkGateway::class)]`; inject `#[Target('neural_network')] HttpClientInterface`, credentials DTO, serializer (named instance if snake_case needed), `LoggerService`, and mappers
- [x] 3.4 Implement all port methods: validate input before HTTP; `json` body via serializer normalize; compatible POST bodies include `stream: false`; apply `auth_bearer` only when key is non-empty
- [x] 3.5 On the port implementation: rethrow `CoreException`; catch `Throwable` and wrap into transport with `previous`; treat non-success HTTP and provider `error` payloads as transport; log request info without the key

## 4. Mappers (`docs/mappers.md`)

- [x] 4.1 Add Transport mappers under `Infrastructure/Transport/NeuralNetwork/Mapper/` with postfix `Mapper` and method `map` for each response shape (models list native + compatible, chat/completion/response/messages, embeddings, load, download job, download status); nested objects get a dedicated mapper
- [x] 4.2 Mapper constructors may depend only on other mappers (no HttpClient, env, logger, cache)
- [x] 4.3 Client uses mappers instead of inlined array-to-DTO mapping

## 5. Tests (`docs/testing.md`)

- [x] 5.1 Unit test `tests/Unit/Infrastructure/Transport/NeuralNetwork/NeuralNetworkApiClientTest.php` with `CoversClass` / `CoversMethod` for every port method: happy paths, empty model lists, validation (no HTTP), empty host, HTTP failure, empty key (no Authorization), non-empty key (Bearer); MockHttpClient only
- [x] 5.2 Unit tests for each new mapper at `tests/Unit/Infrastructure/Transport/NeuralNetwork/Mapper/{Name}MapperTest.php` with `CoversClass` / `CoversMethod` for `map`
- [x] 5.3 Run the Unit suite and PHPStan (`src/` + `tests/`) at level max
