SHELL=/bin/bash
APP_ID=тут название проекта

init: up \
		composer-install \
		composer-dump-autoload

composer-install:
	docker exec ${APP_ID}_php composer install --no-plugins
composer-dump-autoload:
	docker exec ${APP_ID}_php composer dump-autoload
up:
	docker compose -f .docker.loc/docker-compose.yml up -d --build --remove-orphans
down:
	docker compose -f .docker.loc/docker-compose.yml down --remove-orphans
ps:
	docker compose -f .docker.loc/docker-compose.yml ps
bash:
	docker exec -it ${APP_ID}_php bash
root:
	docker exec -u root -it ${APP_ID}_php bash
migrate:
	docker exec -u root -it ${APP_ID}_php php bin/console --no-interaction doctrine:migrations:migrate
fix:
	docker compose -f .docker.loc/docker-compose.yml run -T --rm --no-deps php php vendor/bin/phpcbf --standard=ruleset.xml src
check:
	docker compose -f .docker.loc/docker-compose.yml run -T --rm --no-deps php php vendor/bin/phpcs --standard=ruleset.xml src
lint:
	docker compose -f .docker.loc/docker-compose.yml run -T --rm --no-deps php php /app/vendor/bin/phpstan analyse src
tests:
	docker exec ${APP_ID}_php php ./vendor/phpunit/phpunit/phpunit
