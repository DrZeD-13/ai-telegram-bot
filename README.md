# ai-telegram-bot

Telegram-бот и консольный ИИ-агент: диалог с нейросетью, инструмент shell, история сессий.

Агент отвечает на русском. В Telegram и в консоли один и тот же цикл: сессия с UUID, `/new` начинает новую (старая история сохраняется), `/open <uuid>` возвращает к сохранённой.

## Локальный запуск

1. Скопируйте Docker-окружение и заполните значения (`SERVICE_NAME`, `NGINX_PORT`, `MYSQL_*`, токен бота, хост нейросети):

   ```bash
   cp .docker.loc/.env.example .docker.loc/.env
   ```

2. Поднимите стек (nginx, php-fpm, MariaDB):

   ```bash
   make up
   ```

   Либо:

   ```bash
   docker compose -f .docker.loc/docker-compose.yml --env-file .docker.loc/.env up -d --build
   ```

3. Установите PHP-зависимости в контейнере `php` (рабочий каталог `/var/www`):

   ```bash
   make composer-install
   ```

4. Примените миграции:

   ```bash
   make migrate
   ```

5. Откройте `http://localhost:8080` (`NGINX_PORT` из `.docker.loc/.env`). MariaDB с хоста доступна на `MYSQL_PORT` (в примере `3338`; смените порт, если он занят).

Контейнер PHP называется `${SERVICE_NAME}_php` (например `ai-telegram-bot_php`). Дальше в примерах — `ai-telegram-bot_php`; подставьте своё имя, если `SERVICE_NAME` другой.

Полезные цели Makefile: `make bash` — shell в контейнере PHP, `make down` — остановить стек, `make lint` — PHPStan.

## Консольный агент

Интерактивный диалог в терминале, как в Telegram. Нужен TTY (`-it`), иначе ввод не заработает.

```bash
docker exec -it ai-telegram-bot_php php bin/console agent:chat
```

Продолжить сохранённую сессию:

```bash
docker exec -it ai-telegram-bot_php php bin/console agent:chat --session-id=01df73ed-3ca4-51c5-bca9-c595ad0ca7be
```

Внутри диалога:

| Команда | Действие |
| --- | --- |
| текст | отправить сообщение агенту |
| `/new` | новая сессия, история предыдущей не удаляется, бот покажет оба UUID |
| `/open <uuid>` | переключиться на свою сохранённую сессию |
| `/exit` или `/quit` | выйти |

## Другие команды

Запускаются в контейнере PHP:

```bash
docker exec -it ai-telegram-bot_php php bin/console <команда>
```

| Команда | Назначение |
| --- | --- |
| `agent:chat` | консольный диалог с агентом |
| `telegram:process-incoming` | поллинг входящих сообщений Telegram (цикл раз в минуту, один процесс) |
| `conversation:purge-old-history` | удалить историю диалогов старше одного месяца (для cron) |
| `doctrine:migrations:migrate` | применить миграции БД |

Пример cron для очистки истории:

```cron
0 3 * * * docker exec ai-telegram-bot_php php bin/console conversation:purge-old-history
```
