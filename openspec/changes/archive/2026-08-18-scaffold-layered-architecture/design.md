## Context

Symfony 7 skeleton: `src/Kernel.php`, пустые `src/Controller`, `src/Entity`, `src/Repository`; Doctrine mapping `src/Entity` / `App\Entity`; `config/routes.yaml` импортирует `routing.controllers` (по умолчанию `src/Controller`); `config/services.yaml` сканирует весь `src/`. Правила слоёв — в `docs/architecture.md` (см. proposal.md — Why).

## Goals / Non-Goals

**Goals:**

- Совместить filesystem + Doctrine + routes + DI с `docs/architecture.md`.
- Сохранить пустые каталоги в git без прикладных классов.

**Non-Goals:**

- Доменные entity, сервисы Application, реализации Persistence, контроллеры, команды.
- Deptrac/PHPStan-правила на направление зависимостей между слоями.
- Каталоги Elasticsearch/OpenSearch/Telegram — появляются вместе с интеграцией, не заранее.
- Правки `docs/architecture.md` и `config/reference.php`.

## Decisions

### 1. Placeholder — `.gitignore` как в skeleton

**Выбор:** в каждом новом пустом каталоге пустой `.gitignore` (как сейчас в `src/Entity`), чтобы git хранил папку.

**Альтернатива:** `.gitkeep` — тоже работает, но расходится со skeleton.

### 2. Удалить штатные каталоги skeleton

**Выбор:** удалить `src/Controller`, `src/Entity`, `src/Repository` целиком. Прикладной код туда не кладётся.

**Альтернатива:** оставить и игнорировать — путаница с двумя местами для entity/контроллеров.

### 3. Явный import контроллеров, не `routing.controllers`

**Выбор:** в `config/routes.yaml` задать resource на `../src/Presentation/Http/Controller/` с `type: attribute` (и при необходимости `namespace: App\Presentation\Http\Controller`).

**Почему не `routing.controllers`:** дефолт Symfony указывает на `src/Controller`.

### 4. Doctrine mapping `App\Domain\Entity`

**Выбор:** в `config/packages/doctrine.yaml` у текущего mapping `App` сменить `dir` на `%kernel.project_dir%/src/Domain/Entity` и `prefix` на `App\Domain\Entity`. Alias можно оставить `App` или сменить на `Domain` — оставить `App`, чтобы не плодить ни на что не завязанные переименования.

**Альтернатива:** отдельный mapping `Domain` рядом со старым `App` — старый путь тогда всё ещё «существует» в конфиге.

### 5. DI: один resource `App\` с exclude

**Выбор:**

```yaml
App\:
    resource: '../src/'
    exclude:
        - '../src/Domain/Entity/'
        - '../src/Kernel.php'
```

Autoconfigure по-прежнему подхватит будущие контроллеры и команды из Presentation. Отдельный `resource` только для контроллеров не обязателен, пока нет классов.

**Альтернатива:** несколько resource по слоям — лишняя конфигурация на пустом дереве.

### 6. Infrastructure только `Persistence`

**Выбор:** создать `Infrastructure/Persistence/` (внутри можно сразу `Repository/` как место репозиториев). Не создавать `Elasticsearch/`, `OpenSearch/`, `Telegram/`.

## Risks / Trade-offs

- [MakerBundle / дефолтные команды Symfony всё ещё генерируют в `src/Entity` и `src/Controller`] → генерировать вручную в слои; maker не входит в этот change.
- [Пустой `Domain/Entity` и `validate_xml_mapping`] → attribute mapping пустой папки должен быть валиден; проверить `bin/console about` / cache:clear.
- [Исключение Entity из DI] → нормально: entity создаёт Doctrine, не контейнер.

## Migration Plan

1. Создать дерево каталогов и placeholder `.gitignore`.
2. Обновить `doctrine.yaml`, `routes.yaml`, `services.yaml`.
3. Удалить `src/Controller`, `src/Entity`, `src/Repository`.
4. `bin/console cache:clear` (или `list`) — приложение бутится.

Откат: вернуть skeleton-пути и каталоги.

## Open Questions

Нет.
