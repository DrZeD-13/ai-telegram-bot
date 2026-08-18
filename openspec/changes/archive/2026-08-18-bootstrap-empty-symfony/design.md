## Context

Репозиторий почти пустой: OpenSpec, `phpstan.neon` (пути `bin/`, `config/`, `src/`, `tests/`) и шаблон стека `.docker.loc`. Nginx уже настроен на `/var/www/public/`, PHP 8.4-fpm (dev Dockerfile), MariaDB 10.5.3, volume `../:/var/www`. Compose-пример читает `.env` рядом с собой; Symfony читает `.env` в корне монтируемого дерева (`/var/www`). См. `proposal.md` (Why) и specs `symfony-app`, `local-docker`.

## Goals / Non-Goals

**Goals:**

- Получить рабочий Symfony 7 skeleton в корне и `docker compose up` из `.docker.loc` без правок Dockerfiles.
- Развести секреты/порты compose и runtime Symfony, сохранив совместимый `DATABASE_URL`.
- Оставить `*.example` неизменными.

**Non-Goals:**

- Telegram-бот, очереди, Messenger, фронтенд, CI, prod-образ php-fpm.
- Рефакторинг `.docker.loc/context`.
- Кастомные сущности Doctrine и миграции домена.

## Decisions

### 1. Symfony 7 skeleton в корне, не в подкаталоге

**Выбор:** `composer create-project symfony/skeleton:"^7.2"` (или актуальный 7.x) в корне репозитория, PHP `^8.4`. Пакеты: `symfony/runtime`, Doctrine (`orm`, `doctrine-bundle`, `doctrine-migrations`) + MySQL driver, чтобы `DATABASE_URL` из шаблона имел смысл.

**Почему не webapp:** «пустой проект» — без Twig/Asset/Stimulus. Nginx нужен только `public/index.php`.

**Альтернатива:** `symfony/webapp` — лишние бандлы. Подкаталог `app/` — ломает volume и nginx `root /var/www/public/`.

Создавать проект лучше в PHP-контейнере (Composer уже в образе), во временный каталог и перенос файлов, либо `create-project` с осторожностью относительно уже лежащих `.docker.loc` / `openspec`. Практичный путь: `composer create-project` в `/tmp` внутри контейнера, затем скопировать файлы skeleton в `/var/www`, не затирая `.docker.loc`, `openspec`, `.cursor`, `phpstan.neon`, `.gitignore`.

### 2. Два env-файла

**Выбор:**

| Файл | Роль |
|------|------|
| `.docker.loc/.env` | подстановка compose: `SERVICE_NAME`, порты, `MYSQL_*` |
| корневой `.env` | Symfony: `APP_ENV`, `APP_SECRET`, `DATABASE_URL` |

`.docker.loc/.env` создаётся копией `.env.example` с заполненными значениями и **добавляется в gitignore** (пароль). Значения по умолчанию: `SERVICE_NAME=ai-telegram-bot`, `NGINX_PORT=8080`, `MYSQL_PORT=3338`, `MYSQL_DATABASE=ai_telegram_bot`, `MYSQL_USER=root`, `MYSQL_PASSWORD=password` (как в example).

Корневой `.env` генерируется Symfony; `DATABASE_URL` выровнять с example:

`mysql://root:password@mysql:3306/ai_telegram_bot?serverVersion=mariadb-10.5.3`

В репозиторий коммитится `.env` Symfony без секретов прод-уровня **или** только `.env` skeleton + `.env.local` в gitignore. Предпочтение: коммитить корневой `.env` как шаблон dev (как делает Symfony), не коммитить `.docker.loc/.env`.

**Почему не один файл в `.docker.loc`:** PHP-контейнер не прокидывает `MYSQL_*` в Symfony; приложение не читает `.docker.loc/.env`.

### 3. Рабочий compose коммитим, example не трогаем

**Выбор:** скопировать `docker-compose.yml.example` → `docker-compose.yml` без правок сервисов. Compose в git, чтобы `up` работал после clone (нужен только локальный `.env`).

**Альтернатива:** запускать `docker compose -f docker-compose.yml.example` — хрупко из-за имени файла и привычки команды.

### 4. README только про локальный запуск

Краткий блок: скопировать `.env`, `docker compose up --build`, `composer install` в php, открыть `http://localhost:8080`. Не описывать архитектуру бота.

## Risks / Trade-offs

- [create-project затрёт существующие файлы] → генерировать во `/tmp` и копировать выборочно; не перезаписывать `.gitignore` слепо — смержить Symfony-блоки с текущими.
- [конфликт порта 8080/3338] → меняется только локальный `.docker.loc/.env`.
- [phpstan ждёт vendor и пути skeleton] → после установки `phpstan/phpstan-doctrine` должен совпасть с `phpstan.neon`; если расширение ещё не в composer — добавить dev-зависимость или временно не ломать neon.
- [смешение Symfony-переменных в `.docker.loc/.env.example`] → не правим example в этом change; корневой `.env` — источник для приложения.

## Migration Plan

1. Поднять compose из копии example.
2. Положить skeleton в корень.
3. Выровнять `DATABASE_URL` и gitignore (`.docker.loc/.env`).
4. Проверить `bin/console list` и HTTP на nginx-порту.

Откат: удалить сгенерированные Symfony-файлы и `.docker.loc/docker-compose.yml`; шаблоны остаются.

## Open Questions

- Точный patch-релиз Symfony 7.x на момент apply (фиксировать `^7.2` в composer).
- Нужен ли сразу `symfony/maker-bundle` в require-dev — не влияет на specs; по умолчанию не ставим.
