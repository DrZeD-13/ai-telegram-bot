# Mapper

Правила слоёв — в [architecture.md](architecture.md).

**Mapper** — объект, который преобразует одно представление данных в другое: ответ/тело внешнего API → DTO, Request HTTP → Application DTO, Application DTO / entity → Response HTTP. Не фабрика (не выбирает *какой* класс создать) и не билдер (нет пошаговой сборки).

Имя класса — с постфиксом `Mapper`. Метод преобразования — `map`.

Примеры имён: `ExampleItemResponseMapper`, `ExampleRequestDtoMapper`.

## Чистота

Mapper — чистая функция с состоянием только из других mapper’ов.

**Можно** в конструкторе:

- другие `*Mapper`

**Нельзя:**

- HttpClient, репозиторий, EntityManager, порт Application, use case
- часы, random, логгер, кэш, env, `RequestStack`
- ходить в сеть или в БД «за добрать поле»

Нужны данные снаружи маппинга — их добывает сервис/клиент **до** вызова `map` и передаёт аргументом.

Вложенный объект не копировать полями в нескольких mapper’ах: вынести `ExampleItemMapper` и внедрить его туда, где item вложен.

## Где лежит

Рядом с целевым представлением:

- HTTP response/request — `Presentation/Http/Mapper/` (или `.../Request/`, `.../Response/`)
- разбор ответа внешнего API — `Infrastructure/Transport/{System}/Mapper/`
- проекция Application, если нужна — `Application/.../Mapper/`

Контроллер не собирает nested response руками: `return $this->json($this->exampleResponseMapper->map($dto))`.

## Пример: объект с вложенными mapper’ами

Application отдаёт `ExampleAggregateDto`: вложенный `ExampleItemDto` (тот же shape, что в списке) плюс собственные поля. Item маппит один `ExampleItemResponseMapper` — без дублирования полей в списке и в агрегате.

```php
final readonly class ExampleItemResponseMapper
{
    public function map(ExampleItemDto $dto): ExampleItemResponse
    {
        return new ExampleItemResponse(
            id: $dto->id,
            name: $dto->name,
        );
    }
}

final readonly class ExampleListResponseMapper
{
    public function __construct(
        private ExampleItemResponseMapper $itemMapper,
    ) {
    }

    public function map(ExampleListDto $dto): ExampleListResponse
    {
        $items = [];
        foreach ($dto->items as $item) {
            $items[] = $this->itemMapper->map($item);
        }

        return new ExampleListResponse($items);
    }
}

final readonly class ExampleAggregateResponseMapper
{
    public function __construct(
        private ExampleItemResponseMapper $itemMapper,
    ) {
    }

    public function map(ExampleAggregateDto $dto): ExampleAggregateResponse
    {
        $lines = [];
        foreach ($dto->lines as $line) {
            $lines[] = new ExampleLineResponse(
                id: $line->id,
                quantity: $line->quantity,
            );
        }

        return new ExampleAggregateResponse(
            item: $this->itemMapper->map($dto->item),
            createdAt: $dto->createdAt,
            lines: $lines,
        );
    }
}
```

Обратное направление (HTTP request → Application DTO) — тот же постфикс:

```php
final readonly class ExampleDtoMapper
{
    public function map(ExampleRequest $request): ExampleDto
    {
        return new ExampleDto(
            name: $request->name,
            lines: array_map(
                static fn (ExampleRequestLine $line): ExampleLineDto => new ExampleLineDto(
                    id: $line->id,
                    quantity: $line->quantity,
                ),
                $request->lines,
            ),
        );
    }
}
```

Набор элементов, который уходит из слоя как результат метода, по-прежнему коллекция, не голый `array` — [architecture.md](architecture.md). Внутри `map` локальный `$items = []` допустим, пока наружу отдаётся объект ответа/коллекции.

## Не делать

- Имена `*Factory`, `*Builder`, `*Converter`, `*Assembler` для этих преобразований.
- Один «бог-mapper» на весь модуль: дробить по целевому типу, вкладывать мелкие.
- Mapper, который ходит в репозиторий «подгрузить связанную сущность».
