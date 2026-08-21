## Purpose

Даёт приложению транспорт к HTTP API нейросетей: инференс (чат, completions, embeddings, responses, messages), список моделей и управление локальными моделями, без встраивания HTTP, хоста и ключа в сценарии. Новый провайдер подключается отдельным origin и ключом при том же контракте.

## ADDED Requirements

### Requirement: List models via native API

The system SHALL list models available at the configured neural network origin using the native models listing operation. The result MUST be a typed collection of models (not a raw JSON array). Each item MUST include at least a model identifier. An empty catalog MUST return an empty collection and MUST NOT be treated as an error.

#### Scenario: Models are returned

- **WHEN** the provider has one or more models
- **THEN** the system returns a collection of those models with identifiers

#### Scenario: Empty catalog

- **WHEN** the provider has no models
- **THEN** the system returns an empty collection and does not treat this as an error

### Requirement: Chat via native API

The system SHALL send a native chat request with a model identifier and a non-empty sequence of messages. On success it MUST return a typed chat result that includes the assistant output text when the provider returned one. Blank model identifier or empty messages MUST be rejected before any HTTP call.

#### Scenario: Successful native chat

- **WHEN** the caller sends a valid model id and at least one message
- **THEN** the provider receives the chat request and the system returns a typed result with assistant text when present

#### Scenario: Invalid native chat input is rejected

- **WHEN** the caller sends a blank model id or no messages
- **THEN** the system does not call the provider and reports a validation failure

### Requirement: Load a model

The system SHALL request that the provider load a model identified by a non-empty model identifier. On success it MUST return a typed load result that reflects provider status. A blank model identifier MUST be rejected before any HTTP call.

#### Scenario: Successful load

- **WHEN** the caller requests load of a known model id
- **THEN** the provider receives the load request and the system returns a typed load result

#### Scenario: Blank model id is rejected

- **WHEN** the caller requests load with a blank model identifier
- **THEN** the system does not call the provider and reports a validation failure

### Requirement: Download a model

The system SHALL request that the provider start downloading a model identified by a non-empty identifier (for example a Hugging Face model key). On success it MUST return a typed download job that includes a job identifier. A blank identifier MUST be rejected before any HTTP call.

#### Scenario: Successful download start

- **WHEN** the caller requests download of a non-empty model identifier
- **THEN** the provider receives the download request and the system returns a job identifier

#### Scenario: Blank download identifier is rejected

- **WHEN** the caller requests download with a blank identifier
- **THEN** the system does not call the provider and reports a validation failure

### Requirement: Download job status

The system SHALL fetch the status of a model download job by job identifier. On success it MUST return a typed status object that includes the job identifier and a status value. A blank job identifier MUST be rejected before any HTTP call.

#### Scenario: Status is returned

- **WHEN** the caller requests status for an existing job id
- **THEN** the system returns that job id and a status value from the provider

#### Scenario: Blank job id is rejected

- **WHEN** the caller requests status with a blank job identifier
- **THEN** the system does not call the provider and reports a validation failure

### Requirement: List models via OpenAI-compatible API

The system SHALL list models using the OpenAI-compatible models listing operation. The result MUST be a typed collection of models with at least an identifier per item. An empty list MUST return an empty collection.

#### Scenario: Compatible models are returned

- **WHEN** the compatible models endpoint returns one or more models
- **THEN** the system returns a typed collection of those models

### Requirement: Create a response

The system SHALL send an OpenAI-compatible responses request with a non-empty model identifier and non-empty input. On success it MUST return a typed response object that includes an identifier and output text when the provider returned them. Blank model or blank input MUST be rejected before any HTTP call.

#### Scenario: Successful response

- **WHEN** the caller sends a valid model id and input
- **THEN** the provider receives the request and the system returns a typed response result

#### Scenario: Invalid response input is rejected

- **WHEN** the caller sends a blank model id or blank input
- **THEN** the system does not call the provider and reports a validation failure

### Requirement: Chat completions

The system SHALL send an OpenAI-compatible chat completions request with a non-empty model identifier and a non-empty sequence of messages. On success it MUST return a typed completion that includes assistant text when present. Blank model or empty messages MUST be rejected before any HTTP call. Streaming MUST NOT be used in this capability.

#### Scenario: Successful chat completion

- **WHEN** the caller sends a valid model id and at least one chat message
- **THEN** the provider receives a non-streaming chat completions request and the system returns a typed result with assistant text when present

#### Scenario: Invalid chat completion input is rejected

- **WHEN** the caller sends a blank model id or no messages
- **THEN** the system does not call the provider and reports a validation failure

### Requirement: Text completions

The system SHALL send an OpenAI-compatible completions request with a non-empty model identifier and non-empty prompt. On success it MUST return a typed completion that includes generated text when present. Blank model or blank prompt MUST be rejected before any HTTP call. Streaming MUST NOT be used.

#### Scenario: Successful text completion

- **WHEN** the caller sends a valid model id and prompt
- **THEN** the provider receives a non-streaming completions request and the system returns a typed result

#### Scenario: Invalid completion input is rejected

- **WHEN** the caller sends a blank model id or blank prompt
- **THEN** the system does not call the provider and reports a validation failure

### Requirement: Embeddings

The system SHALL send an OpenAI-compatible embeddings request with a non-empty model identifier and non-empty input text. On success it MUST return a typed embeddings result whose vectors are a typed collection (not a raw array of arrays). Blank model or blank input MUST be rejected before any HTTP call.

#### Scenario: Successful embeddings

- **WHEN** the caller sends a valid model id and input text
- **THEN** the provider receives the embeddings request and the system returns a typed collection of embedding vectors

#### Scenario: Invalid embeddings input is rejected

- **WHEN** the caller sends a blank model id or blank input
- **THEN** the system does not call the provider and reports a validation failure

### Requirement: Anthropic-compatible messages

The system SHALL send an Anthropic-compatible messages request with a non-empty model identifier, a positive max-tokens value, and a non-empty sequence of messages. On success it MUST return a typed message result that includes assistant text when present. Blank model, non-positive max-tokens, or empty messages MUST be rejected before any HTTP call. Streaming MUST NOT be used.

#### Scenario: Successful messages request

- **WHEN** the caller sends a valid model id, positive max-tokens, and at least one message
- **THEN** the provider receives a non-streaming messages request and the system returns a typed result

#### Scenario: Invalid messages input is rejected

- **WHEN** the caller sends a blank model id, max-tokens less than or equal to zero, or no messages
- **THEN** the system does not call the provider and reports a validation failure

### Requirement: Provider host from environment

The neural network origin MUST be read from the environment variable `NEURAL_NETWORK_API_HOST`. Requests MUST use a scoped HTTP client bound to that origin and MUST call relative paths (not an absolute host hardcoded in PHP). The first provider origin is the local machine `http://127.0.0.1:1234`. Committed env templates MAY document that local origin as the default for development. Host and API key MUST be separate variables.

#### Scenario: Host is configured via env

- **WHEN** `NEURAL_NETWORK_API_HOST` is set to a provider origin
- **THEN** all neural network operations call that origin with relative API paths

#### Scenario: Paths are relative to the scoped client

- **WHEN** the client lists models or runs inference
- **THEN** the HTTP request URI is relative to `NEURAL_NETWORK_API_HOST` and does not embed a hardcoded host in PHP

#### Scenario: Missing host fails closed

- **WHEN** any neural network operation is invoked and `NEURAL_NETWORK_API_HOST` is empty or unset
- **THEN** the system does not call the provider and reports a configuration error

### Requirement: Optional API key from environment

The API key MUST be read from the environment variable `NEURAL_NETWORK_API_KEY`. The committed env templates MUST declare the variable without a live secret. When the key is empty, requests MUST be sent without an Authorization header (local provider without auth). When the key is non-empty, requests MUST authenticate with Bearer using that key. The key MUST NOT appear in source code.

#### Scenario: Empty key skips authorization

- **WHEN** `NEURAL_NETWORK_API_KEY` is empty and the host is configured
- **THEN** operations call the provider without an Authorization header

#### Scenario: Non-empty key is sent as Bearer

- **WHEN** `NEURAL_NETWORK_API_KEY` is set to a non-empty value
- **THEN** operations send that value as a Bearer credential

#### Scenario: Committed files have no secret

- **WHEN** a developer inspects committed `.env` / env examples
- **THEN** `NEURAL_NETWORK_API_KEY` is present as an empty or placeholder value and does not contain a live key

### Requirement: Additional providers via host and key

A new neural network provider MUST be addable without changing the Application port: a separate origin env and a separate API key env, bound to a dedicated scoped HTTP client. Application scenarios MUST depend on the Application port, not on a concrete provider class. The local provider implemented in this change MUST satisfy the full port (native `/api/v1/*` and compatible `/v1/*` operations).

#### Scenario: Scenarios use the port

- **WHEN** an Application scenario needs to call a neural network
- **THEN** it depends on the Application neural network port and not on an Infrastructure HTTP client type

#### Scenario: New provider is configuration plus client

- **WHEN** a later change adds another provider
- **THEN** that provider is introduced as its own host env, key env, and scoped client while keeping the same Application port operations

### Requirement: Thirty-minute HTTP timeout

The neural network HTTP client MUST use a request timeout of 30 minutes (1800 seconds). It MUST NOT inherit the application's default short HTTP timeout for this integration. A request that does not complete within 30 minutes MUST fail as a transport error.

#### Scenario: Client timeout is thirty minutes

- **WHEN** a developer inspects the neural network scoped HTTP client configuration
- **THEN** the timeout is 30 minutes

#### Scenario: Overdue request fails as transport

- **WHEN** a neural network request does not complete within 30 minutes
- **THEN** the operation fails with a port transport error and does not return a success DTO or collection

### Requirement: Provider errors surface to the caller

When the provider responds with a transport failure or a non-success HTTP status, the system MUST fail the operation. Partial or silent success MUST NOT be reported. Failures that cross the Application port MUST be types that extend `CoreException` and MUST be declared on the port. HttpClient, JSON, and `\Error` MUST NOT leak through the port.

#### Scenario: HTTP transport failure

- **WHEN** the HTTP request cannot complete (timeout, connection error, or non-success HTTP status)
- **THEN** the operation fails and does not return a success DTO or collection

#### Scenario: Unexpected errors are wrapped

- **WHEN** an operation hits an unexpected throwable (including HTTP client or JSON failures)
- **THEN** the caller receives a port exception that extends `CoreException`, with the original throwable as `previous`

### Requirement: External payloads are mapped by Mapper types

Conversion from provider JSON into Application DTOs MUST be done by classes named with postfix `Mapper` and a method `map`, living under the neural network transport. Nested objects MUST use a dedicated mapper rather than copying fields in several places. Mapper constructors MUST NOT depend on HttpClient, env, logger, or cache.

#### Scenario: Client does not inline nested mapping

- **WHEN** a neural network operation succeeds with a JSON payload
- **THEN** Application DTOs are produced via `Mapper::map`, not by assembling nested fields inside the HTTP client

### Requirement: Tests follow project test conventions

Automated tests MUST follow `docs/testing.md`. They MUST live under `tests/Unit/` or `tests/Functional/` with a path after that root matching the production class under `src/`. The test class MUST be named `{ClassName}Test`. Each test class MUST declare coverage with `CoversClass` and `CoversMethod` for every production method it exercises. This change MUST add unit tests for the neural network HTTP client and for each new mapper, and MUST NOT place those tests in `tests/Functional/` unless the test boots the application kernel.

#### Scenario: Unit test path matches the HTTP client

- **WHEN** a unit test covers the neural network HTTP client class
- **THEN** the file is `tests/Unit/Infrastructure/Transport/NeuralNetwork/{ClassName}Test.php` where `{ClassName}` is the production class name

#### Scenario: Coverage names the class and methods

- **WHEN** a developer inspects the neural network HTTP client unit test
- **THEN** the test class declares coverage of that production class and of every client method the test exercises (all port operations)

#### Scenario: Mapper tests mirror mapper classes

- **WHEN** a unit test covers a neural network transport mapper
- **THEN** the file is `tests/Unit/Infrastructure/Transport/NeuralNetwork/Mapper/{ClassName}Test.php` and declares `CoversClass` / `CoversMethod` for `map`

#### Scenario: Tests are not dumped at tests root

- **WHEN** a developer inspects `tests/` for this capability
- **THEN** test files are not placed directly in `tests/` or in a path that omits `Unit`/`Functional` or the Infrastructure/Transport/NeuralNetwork segments
