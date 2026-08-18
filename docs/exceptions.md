# Исключения

Правила слоёв — в [architecture.md](architecture.md). Здесь — какие исключения где лежат и что можно выбрасывать через границу слоя.

## Аннотация `@throws`

Метод, который выбрасывает **любое** исключение, обязан перечислить эти типы в PHPDoc:

```php
/**
 * @throws CoreException
 */
public function save(Order $order): void
{
}
```

Несколько типов — несколько строк `@throws`. Реализация интерфейса не расширяет набор относительно контракта: наружу только то, что объявлено на методе порта/интерфейса. Не писать `@throws \Throwable` или `@throws \Exception`, если это не явный контракт метода.

## Иерархия

Корневое доменное исключение:

`src/Domain/Exception/CoreException.php`

Все **доменные** исключения наследуют его. Не плодить параллельные корни (`extends \RuntimeException` в обход `CoreException`).

```php
<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class CoreException extends \Exception
{
}
```

Типичные наследники в том же каталоге — по бизнес-смыслу, не по HTTP-коду фреймворка:

- `NotFoundException` — сущности/данных нет
- `ConflictException` — конфликт состояния (уже существует, нельзя перейти)
- другие, если появляется устойчивый смысл в домене

```php
namespace App\Domain\Exception;

class NotFoundException extends CoreException
{
}
```

## Domain

Домен бросает только `CoreException` и его наследников и указывает их в `@throws` метода. Сообщение понятное для вызывающего сценария. `previous` — если оборачивается техническая причина.

## Application

Сервис Application, если бросает исключение, бросает **доменное**, соответствующее бизнес-логике (`NotFoundException`, `ConflictException`, …), а не `\RuntimeException`, не Symfony HttpException и не тип из Infrastructure.

Для портов (интерфейсов, которые реализует Infrastructure) слой Application **может** завести свои исключения в `src/Application/Exception/`. Имеет смысл, когда сбой интеграции — не «не найдено в домене», а контракт порта (транспорт, конфигурация клиента). Если по смыслу подходит доменный тип — использовать его, не дублировать.

На методе порта, сервиса и любого другого метода Application — `@throws` у каждого метода, который бросает исключение; в аннотации только типы, которые реально являются контрактом. Наследник `CoreException` входит в `@throws CoreException`.

## Infrastructure

Может иметь свои исключения (`Infrastructure/Transport/{System}/Exception/…`) для внутренней работы (низкоуровневый `request()`, маппинг JSON).

Когда класс **реализует интерфейс Application** (или Domain), наружу уходят **только** типы из `@throws` этого интерфейса. Чужое — в том числе `\Error`, `\TypeError`, клиент HTTP, Doctrine, JSON — поймать и обернуть. Уже доменное/`CoreException` (и наследники, объявленные на порте) **не** заворачивать второй раз.

```php
/**
 * @throws CoreException
 */
public function bar(): void
{
    try {
        // тут произошло неизвестное исключение
    } catch (CoreException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        throw new CoreException(
            message: 'Понятное сообщение что произошло',
            previous: $exception,
        );
    }
}
```

Если порт объявляет узкий набор (`@throws NotFoundException`, `@throws TelegramBotTransportException`), неизвестное оборачивать в **один из объявленных** типов (обычно общий контракт порта, наследник `CoreException`), не в «левый» Infrastructure exception.

Пустой токен/хост до сетевого вызова — тоже тип с порта (часто configuration-исключение Application, наследник `CoreException`), не сырой `\InvalidArgumentException`.

## Presentation

Ловит `CoreException` (и наследников) с границы Application и мапит в ответ канала. Не зависит от классов Infrastructure. Не тащить `\Throwable` с порта «как есть» в HTTP, если Application/Infrastructure уже обязаны обернуть.

## Не делать

- Выпускать из метода исключение без `@throws` на этом методе.
- Бросать из порта Infrastructure-исключение, которого нет в `@throws`.
- Давать `\Error` / HttpClient exception пролететь через реализацию порта.
- Ловить `CoreException` и заворачивать в новый `CoreException` без нужды (теряется тип `NotFoundException`).
- Заводить Application-исключение, которое не наследует `CoreException`.
- Путать HTTP 404 контроллера с доменным `NotFoundException`: первое — Presentation, второе — смысл «нет сущности».
