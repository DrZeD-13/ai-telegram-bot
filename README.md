# ai-telegram-bot

## Local run

1. Copy Docker env and fill values (`SERVICE_NAME`, `NGINX_PORT`, `MYSQL_*`):

   ```bash
   cp .docker.loc/.env.example .docker.loc/.env
   ```

2. Build and start the stack (nginx, php-fpm, MariaDB):

   ```bash
   docker compose -f .docker.loc/docker-compose.yml --env-file .docker.loc/.env up --build
   ```

3. Install PHP dependencies inside the `php` container (working directory `/var/www`):

   ```bash
   docker exec ${SERVICE_NAME}_php composer install
   ```

4. Open `http://localhost:8080` (`NGINX_PORT` from `.docker.loc/.env`). MariaDB is published on `MYSQL_PORT` (default `3338`; change it in `.docker.loc/.env` if the port is already taken).
