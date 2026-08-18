## 1. Local Docker from `.docker.loc`

- [ ] 1.1 Copy `.docker.loc/docker-compose.yml.example` to `.docker.loc/docker-compose.yml` without changing services (`nginx`, `php`, `mysql`) or Dockerfiles
- [ ] 1.2 Add `.docker.loc/.env` to `.gitignore`; keep `.docker.loc/.env.example` unchanged
- [ ] 1.3 Create `.docker.loc/.env` from the example with `SERVICE_NAME=ai-telegram-bot`, `NGINX_PORT=8080`, `MYSQL_PORT=3338`, `MYSQL_DATABASE=ai_telegram_bot`, `MYSQL_USER=root`, `MYSQL_PASSWORD=password`, `MYSQL_HOST=mysql`
- [ ] 1.4 Build and start the stack from `.docker.loc` (`docker compose up --build`) and confirm containers are healthy

## 2. Empty Symfony skeleton

- [ ] 2.1 Inside the `php` container, create `symfony/skeleton:^7.2` in `/tmp` (PHP `^8.4`) so existing `.docker.loc`, `openspec`, `.cursor`, `phpstan.neon` are not overwritten
- [ ] 2.2 Copy skeleton files into the repository root (`composer.json`, `bin/`, `config/`, `public/`, `src/`, `symfony.lock`, etc.) and merge `.gitignore` with current Symfony/docker entries
- [ ] 2.3 Require Doctrine packages needed for `DATABASE_URL` (`doctrine/orm`, `doctrine/doctrine-bundle`, `doctrine/doctrine-migrations-bundle`, MySQL driver) without adding domain entities
- [ ] 2.4 Align root `.env` (`APP_ENV=dev`, `DATABASE_URL` to `mysql://root:password@mysql:3306/ai_telegram_bot?serverVersion=mariadb-10.5.3`); do not add Telegram/bot code under `src/`
- [ ] 2.5 Add `phpstan/phpstan-doctrine` as a dev dependency so existing `phpstan.neon` resolves, or adjust neon only if the extension cannot be installed yet

## 3. Verify and document

- [ ] 3.1 Run `composer install` in the `php` container at `/var/www` and `bin/console list` successfully
- [ ] 3.2 Confirm `http://localhost:8080/` hits `public/index.php` and MariaDB is reachable on host port `3338`
- [ ] 3.3 Add a short README section: copy `.docker.loc/.env`, `docker compose up --build`, `composer install`, open port 8080
