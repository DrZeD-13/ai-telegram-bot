## Why

Репозиторий пока не содержит приложения: есть только заготовка OpenSpec и шаблон локального стека в `.docker.loc`. Нужна воспроизводимая база — пустой Symfony-проект в корне и локальный Docker, поднятый по этому шаблону, чтобы дальше разрабатывать бота и API в одном окружении.

## What Changes

- Развернуть пустой Symfony 7 (skeleton) в корне репозитория под PHP 8.4, с `public/` как web-root для nginx.
- Подключить Doctrine и `.env` так, чтобы `DATABASE_URL` указывал на MySQL/MariaDB из локального Docker.
- Активировать локальный стек из `.docker.loc`: рабочие `docker-compose.yml` и `.env` на основе `.example`-файлов, без изменения самих шаблонов как источника правды.
- Задокументировать минимальный запуск: сборка контейнеров, `composer install`, проверка HTTP и БД.

## Capabilities

### New Capabilities

- `symfony-app`: пустое Symfony-приложение в корне репозитория (каркас, web-entry, конфигурация окружения и БД).
- `local-docker`: локальная разработка на Docker по шаблону `.docker.loc` (nginx, php-fpm, MariaDB).

### Modified Capabilities

- (нет существующих specs)

## Impact

- Корень репозитория станет Symfony-проектом (`composer.json`, `bin/`, `config/`, `public/`, `src/`, `var/`, `vendor/`).
- Появятся рабочие файлы `.docker.loc/docker-compose.yml` и `.docker.loc/.env` (gitignore для `.env` уже есть; compose обычно коммитится).
- Зависимости: PHP 8.4, Composer, Symfony 7, Doctrine ORM + MySQL, phpstan (уже есть `phpstan.neon`).
- Существующий шаблон `.docker.loc/context/**` и `*.example` не переписываем без необходимости.
