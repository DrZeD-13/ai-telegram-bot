# symfony-app Specification

## Purpose

Provides an empty Symfony application at the repository root so HTTP requests and database configuration work without domain features yet.

## Requirements

### Requirement: Empty Symfony application at repository root

The repository MUST contain a Symfony skeleton application at the project root with a public front controller under `public/`. The application MUST run on PHP 8.4 and MUST NOT ship Telegram bot, domain, or business features as part of this bootstrap.

#### Scenario: Project is a valid Symfony app

- **WHEN** a developer inspects the repository root
- **THEN** Symfony project files exist (`composer.json` with Symfony framework, `config/`, `bin/console`, `public/index.php`, `src/`)
- **AND** `src/` contains no domain or bot-specific code beyond the default Kernel/skeleton

#### Scenario: Front controller is reachable by the web server

- **WHEN** the application is served with document root `public/`
- **THEN** HTTP requests are routed through `public/index.php`

### Requirement: Application environment and database URL

The application MUST load environment configuration from `.env` (and optional `.env.local`). `DATABASE_URL` MUST point at the local Docker database service using host `mysql`, the database name from the Docker env, and MariaDB 10.5.3 as the server version.

#### Scenario: Database URL matches local stack

- **WHEN** a developer reads the committed `.env` template or generated `.env` used with Docker
- **THEN** `DATABASE_URL` uses MySQL/MariaDB, host `mysql`, port `3306` inside the Docker network, and `serverVersion` compatible with MariaDB 10.5.3

#### Scenario: Dev environment is default locally

- **WHEN** the application boots without production overrides
- **THEN** `APP_ENV` is `dev`

### Requirement: Console is usable inside PHP container

The Symfony console MUST be executable from the PHP container working directory `/var/www`.

#### Scenario: Console lists commands

- **WHEN** a developer runs `bin/console list` inside the PHP container after dependencies are installed
- **THEN** the command exits successfully and prints Symfony commands
