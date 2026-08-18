## Why

Правила слоёв уже зафиксированы в `docs/architecture.md`, а `src/` и Symfony-конфиги всё ещё в layout skeleton (`Controller`, `Entity`, `Repository`). Пока каталоги и mapping не приведены к архитектуре, новый код будет ложиться в чужие места.

## What Changes

- Создать каркас слоёв в `src/`: `Application`, `Domain/Entity`, `Infrastructure/Persistence`, `Presentation/Http/Controller`, `Presentation/Console`.
- Убрать прикладное использование штатных каталогов skeleton (`src/Controller`, `src/Entity`, `src/Repository`).
- Направить Doctrine mapping на `Domain/Entity` (`App\Domain\Entity`).
- Направить HTTP-роуты на `Presentation/Http/Controller`.
- Настроить DI так, чтобы сервисы жили в слоях, а entity и Kernel не регистрировались как прикладные сервисы.
- **Не** добавлять доменную логику, сущности, эндпоинты и команды бота — только папки и конфиги.

## Capabilities

### New Capabilities

- `layered-src-layout`: каркас каталогов `src/` и Symfony-конфиги (Doctrine, routes, DI) соответствуют `docs/architecture.md`.

### Modified Capabilities

- (нет опубликованных main specs; bootstrap `symfony-app` не меняем — доменных фич по-прежнему нет)

## Impact

- `src/` layout, `config/packages/doctrine.yaml`, `config/routes.yaml` (и связанные routing-ресурсы), `config/services.yaml`.
- Пустые gitkeep-каталоги вместо классов, кроме уже существующего `Kernel.php`.
- Поведение HTTP/консоли не меняется: прикладных контроллеров и команд нет.
- `docs/architecture.md` — источник правил, в этом change не переписывается.
