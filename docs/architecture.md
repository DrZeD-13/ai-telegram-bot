# Architecture

Проект следует слоистой архитектуре. Исходный код приложения живёт в `src/` (PSR-4: `App\`). Точка входа Symfony (`Kernel.php`) остаётся в корне `src/` и не принадлежит ни одному из слоёв.

Зависимости направлены **внутрь**: Presentation → Application → Domain. Infrastructure реализует порты Application/Domain и не вызывается из Presentation напрямую.

```
Presentation  →  Application  →  Domain
                      ↑
                 Infrastructure
```

## Структура `src/`

```
src/
  Kernel.php
  Application/
  Domain/
    Entity/
  Infrastructure/
    Persistence/
    ...
  Presentation/
    Http/
      Controller/
    Console/
```

Новые каталоги в корне `src/` не заводятся без явной причины. Технические исключения Symfony (`Kernel`) — единственный код вне четырёх слоёв.

## Слои

### Application

Реализация сценариев использования (use cases / application services). Здесь нет инфраструктурных деталей: HTTP-клиентов, SQL, файловой системы, Elasticsearch/OpenSearch, Telegram API и т.п.

Все внешние возможности, которые нужны сценарию, описываются **интерфейсами** (портами). Интерфейс лежит в Application, если это контракт сценария; в Domain — если это ключевая доменная абстракция (например, репозиторий агрегата).

**Разрешено знать о:** себе и Domain.

**Запрещено:** зависеть от Infrastructure и Presentation (никаких `use App\Infrastructure\...`, `use App\Presentation\...`, никаких Doctrine/HTTP-клиентов в этом слое).

Слой отдаёт наружу **доменную модель и сервисные DTO** (и коллекции, см. ниже).

`Domain/Entity` — это доменные объекты (по сути доменные DTO). Persistence возвращает именно их: репозиторий в Infrastructure гидратит `Domain/Entity`, Application **может прокинуть эти entity выше в Presentation** без промежуточного маппинга. То же для value objects и прочих типов Domain.

Application **не** отдаёт наружу объекты Infrastructure (клиенты, ORM-специфичные обёртки, поисковые хиты «как пришли из драйвера») и **не** знает о response-моделях Presentation. Если для сценария нужна проекция, которой нет в домене, Application вводит свой сервисный DTO.

### Domain

Бизнес-правила и модель: сущности, value objects, доменные сервисы, доменные исключения, интерфейсы репозиториев и прочих портов, которые являются частью домена. Entity в `Domain/Entity` — каноническое представление данных домена; их можно передавать из Infrastructure через Application в Presentation.

**По умолчанию домен ни от чего не зависит** (ни от Application, ни от Infrastructure, ни от Presentation).

**Исключение:** сущности базы данных лежат в `Domain/Entity` и могут напрямую использовать зависимости Doctrine (атрибуты/аннотации маппинга, типы Doctrine и т.д.). Это единственное разрешённое обращение домена к инфраструктурной библиотеке персистентности. Реализации репозиториев, EntityManager в сервисах домена, QueryBuilder в доменных классах — **не** входят в это исключение.

### Infrastructure

Конкретные реализации портов: репозитории, API-клиенты, работа с файлами, очереди, поисковые движки.

Внутри слоя код **группируется по технологии/контексту**, а не сваливается в одну кучу:

| Контекст | Каталог | Примеры |
|----------|---------|---------|
| Репозитории и сопутствующее | `Infrastructure/Persistence/` | `Repository/`, `Service/`, `Trait/` |
| Elasticsearch | `Infrastructure/Elasticsearch/` | клиент, мапперы, запросы |
| OpenSearch | `Infrastructure/OpenSearch/` | клиент, мапперы, запросы |
| HTTP/внешние API | отдельная папка по системе | например `Infrastructure/Telegram/` |

Новая интеграция = новая папка внутри Infrastructure, внутри неё — привычные типы (`Client`, `Repository`, `Mapper`, `Service`, `Trait`).

Infrastructure **может** зависеть от Domain и Application (реализует их интерфейсы) и от внешних библиотек. Infrastructure **не** зависит от Presentation.

### Presentation

Отображение для пользователя и входные адаптеры: HTTP-эндпоинты и консольные команды.

| Что | Путь |
|-----|------|
| Контроллеры | `Presentation/Http/Controller` |
| Консольные команды | `Presentation/Console` |

**Разрешено знать о:** Domain и Application.

**Запрещено:** зависеть от Infrastructure; обращаться к репозиториям, EntityManager, поисковым клиентам и любым инфраструктурным сервисам напрямую.

Данные в Presentation приходят **только из Application**: доменные entity/VO и/или сервисные DTO (и коллекции). Response/view-модели канала собираются **в этом слое** фабриками или билдерами (`Presentation/Http/...`) из того, что вернул Application. Entity можно читать и маппить в ответ; нельзя тащить в HTTP/CLI «как есть», если формат ответа специфичен для канала.

Контроллер/команда: принять вход → вызвать сервис Application → из entity и/или сервисного DTO собрать DTO ответа → отдать пользователю.

## DTO и коллекции

Метод, который по смыслу возвращает **набор** DTO или entity, **не** возвращает «голый» `array`. Результат оборачивается в коллекцию:

```php
class YourDtoCollection
{
    /**
     * @var YourDto[]
     */
    private array $items;

    public function __construct(YourDto ...$items)
    {
        $this->items = $items;
    }
}
```

Имя коллекции соответствует элементу (`FooDto` → `FooDtoCollection`, `Order` → `OrderCollection`). Коллекция живёт рядом с типом элемента (доменные entity — в Domain, сервисные DTO — в Application, response — в Presentation).

Одиночный объект возвращается как объект, не как массив из одного элемента.

## Правила зависимостей (кратко)

| Слой | Может зависеть от |
|------|-------------------|
| Domain | ничего, кроме Doctrine в `Domain/Entity` |
| Application | Domain |
| Infrastructure | Domain, Application, внешние библиотеки |
| Presentation | Domain, Application |

Циклы между слоями запрещены. Общие примитивы, нужные нескольким слоям без протечки инфраструктуры, живут в Domain (или в узком контракте Application), а не в «Shared» без правил.

## Связь с Symfony

- HTTP-роуты ведут только в `Presentation/Http/Controller`.
- Команды `bin/console` приложения объявляются в `Presentation/Console`.
- Doctrine mapping указывает на `Domain/Entity`, а не на `src/Entity`.
- Реализации репозиториев регистрируются в DI как реализации интерфейсов Application/Domain; потребители получают интерфейс, не класс из Infrastructure.
- Штатные каталоги skeleton (`src/Controller`, `src/Entity`, `src/Repository`) не используются для прикладного кода.
