# local-docker Specification

## Purpose

Gives developers a local Docker stack (nginx, PHP-FPM, MariaDB) derived from the `.docker.loc` examples so the Symfony app can be started without a custom compose file.

## Requirements

### Requirement: Local stack is instantiated from `.docker.loc` examples

Local Docker MUST be based on `.docker.loc/docker-compose.yml.example` and `.docker.loc/.env.example`. Working files MUST be created from those examples. The example files themselves MUST remain the source of truth and MUST NOT be replaced by the working copies.

#### Scenario: Compose file exists and matches the example layout

- **WHEN** a developer prepares local Docker
- **THEN** `.docker.loc/docker-compose.yml` exists and defines services `nginx`, `php`, and `mysql` as in the example
- **AND** `.docker.loc/docker-compose.yml.example` is still present unchanged as the template

#### Scenario: Env file fills required variables

- **WHEN** a developer copies `.docker.loc/.env.example` to `.docker.loc/.env` (or the repo-root env used by compose)
- **THEN** `SERVICE_NAME`, `NGINX_PORT`, `MYSQL_PORT`, `MYSQL_DATABASE`, `MYSQL_USER`, and `MYSQL_PASSWORD` are set to concrete values
- **AND** `MYSQL_HOST` remains `mysql`

### Requirement: Stack mounts the repository as web root

The PHP and nginx services MUST mount the repository root at `/var/www`. Nginx MUST use `/var/www/public/` as the document root. PHP working directory MUST be `/var/www/`.

#### Scenario: Containers see the Symfony tree

- **WHEN** the stack is running
- **THEN** files from the repository root are available at `/var/www` in `php` and `nginx`
- **AND** nginx serves PHP from `/var/www/public/`

### Requirement: Local HTTP and database ports are published

Nginx MUST publish container port 80 to the host port given by `NGINX_PORT`. MySQL MUST publish container port 3306 to the host port given by `MYSQL_PORT`. Default `MYSQL_PORT` in the example is `3338` and MUST be kept unless it conflicts on the host.

#### Scenario: Developer opens the app in a browser

- **WHEN** the stack is up and Symfony is installed
- **THEN** `http://localhost:<NGINX_PORT>/` reaches the Symfony front controller

#### Scenario: Developer connects to DB from the host

- **WHEN** the stack is up
- **THEN** MariaDB is reachable on `127.0.0.1:<MYSQL_PORT>` with the credentials from the env file

### Requirement: Images come from `.docker.loc/context`

Services MUST build from the Dockerfiles already present under `.docker.loc/context/images/` (nginx, php-fpm/dev, mysql/MariaDB 10.5.3). The php-fpm/dev image MUST be based on `php:8.4-fpm`. PHP image MUST include Composer and extensions needed for Symfony and PDO MySQL.

#### Scenario: PHP image can install dependencies

- **WHEN** a developer runs Composer inside the `php` container
- **THEN** Composer is available on PATH and can install the Symfony project into `/var/www`
