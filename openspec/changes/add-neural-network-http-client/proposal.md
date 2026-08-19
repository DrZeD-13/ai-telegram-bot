## Why

Бот должен вызывать модели (чат, completions, embeddings, управление локальными моделями), но в Application нет порта к API нейросетей. Сценарии не должны знать HTTP, хост и ключ. Нужен транспортный клиент по правилам исходящего HTTP и контракт, который можно расширять новыми провайдерами через origin и ключ.

## What Changes

- Добавить порт Application `NeuralNetworkGateway` с полным интерфейсом работы с нейросетями (список моделей, chat, completions, embeddings, responses, messages, load/download моделей и статус download).
- Реализовать первый провайдер: локальный OpenAI/LM Studio-совместимый HTTP API (`http://127.0.0.1:1234`), без авторизации при пустом ключе.
- Вынести origin в `{PROVIDER}_API_HOST` и ключ в `{PROVIDER}_API_KEY`; не хардкодить хост в PHP. Новый провайдер = новый scoped HttpClient + хост + ключ, тот же порт.
- Клиент в `src/Infrastructure/Transport/NeuralNetwork`; маппинг ответов через `*Mapper`; исключения порта наследуют `CoreException`.
- Покрыть клиент и mapper’ы unit-тестами по `docs/testing.md`.

## Capabilities

### New Capabilities

- `neural-network-http-client`: транспорт к API нейросетей — полный набор операций (native `/api/v1/*` и совместимые `/v1/*`), хост и опциональный ключ из env, scoped Symfony HttpClient, расширяемость другими провайдерами.

### Modified Capabilities

- (нет)

## Impact

- Новые классы: порт и DTO/коллекции Application, исключения порта, `NeuralNetworkApiClient`, `ApiUrlEnum`, mapper’ы, credentials DTO.
- Конфигурация: `NEURAL_NETWORK_API_HOST`, `NEURAL_NETWORK_API_KEY`; scoped-клиент `neural_network` в `config/packages/http_clients.yaml` (timeout 30 минут для инференса).
- Тесты: `tests/Unit/Infrastructure/Transport/NeuralNetwork/` (+ `Mapper/`). Functional HTTP + snapshot для этого клиента не обязательны.
- Вне scope: стриминг, use case бота «спросить модель», выбор модели в UI, другие провайдеры кроме локального (только заложенный контракт).
