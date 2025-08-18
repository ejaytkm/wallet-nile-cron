# CRON
init-cron:
	@docker start app exec -it -e XDEBUG_MODE=off wallet-nile-cron /usr/local/bin/composer install

# INSTALL
start:
	@cd environment && docker-compose up -d

build:
	@cd environment && docker-compose up --build -d

rebuild:
	docker-compose up -d --force-recreate --no-build

down:
	@cd environment && docker-compose down

# DEV
app@install:
	@docker start app exec -it -e XDEBUG_MODE=off wallet-nile-cron /usr/local/bin/composer install
	@make app@restart
	@printf "\nApplication dependencies installed successfully.\n"

app@restart:
	@docker restart wallet-nile-cron
	@docker logs -f wallet-nile-cron --tail 10

app@autoload:
	@docker exec -it -e XDEBUG_MODE=off wallet-nile-cron /usr/local/bin/composer dump-autoload

app@swole-version:
	@docker exec -it wallet-nile-cron php -r "echo swoole_version();"
	@printf "\nSwoole version displayed successfully."

## COMMANDS
app@cron:
	@docker exec -it wallet-nile-cron php cron.php

app@cron-requeue:
	@docker exec -it wallet-nile-cron php crond/requeue_jobs.php

app@cron-syncbet:
	@docker exec -it wallet-nile-cron php crond/sync_bet_history.php

## DOCKER
docker@tail-logs:
	@docker logs -f wallet-nile-cron

docker@restart:
	@docker restart wallet-nile-cron
	@printf "Docker wallet-nile environment successfully restarted.\n"

## REDIS
redis@flush:
	@docker exec -it shared-services_redis redis-cli FLUSHALL
	@printf "Redis database flushed successfully.\n"