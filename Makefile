SHELL := /bin/bash

COMPOSE_FILE := .docker.loc/docker-compose.yml
ENV_FILE := .docker.loc/.env
COMPOSE := BUILDKIT_PROGRESS=plain docker compose -f $(COMPOSE_FILE) --env-file $(ENV_FILE)

APP_ID := $(shell grep '^SERVICE_NAME=' $(ENV_FILE) 2>/dev/null | cut -d= -f2- | tr -d '[:space:]')

.PHONY: init up down ps bash root composer-install composer-dump-autoload migrate lint

init: up \
		composer-install \
		composer-dump-autoload

up:
	$(COMPOSE) up -d --build --remove-orphans

down:
	$(COMPOSE) down --remove-orphans

ps:
	$(COMPOSE) ps

composer-install:
	docker exec $(APP_ID)_php composer install

composer-dump-autoload:
	docker exec $(APP_ID)_php composer dump-autoload

bash:
	docker exec -it $(APP_ID)_php bash

root:
	docker exec -u root -it $(APP_ID)_php bash

migrate:
	docker exec $(APP_ID)_php php bin/console --no-interaction doctrine:migrations:migrate --allow-no-migration

lint:
	docker exec $(APP_ID)_php php vendor/bin/phpstan analyse
