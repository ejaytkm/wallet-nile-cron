# INSTALL
start:
	@cd environment && docker-compose up -d

build:
	@cd environment && docker-compose up --build -d

down:
	@cd environment && docker-compose down

app@install:
	@docker start app exec -it -e XDEBUG_MODE=off wallet-nile-cron /usr/local/bin/composer install
	@make app@restart
	@printf "\nApplication dependencies installed successfully.\n"

# DEV
app@restart:
	@docker restart wallet-nile-cron
	@docker logs -f wallet-nile-cron --tail 10

app@autoload:
	@docker exec -it -e XDEBUG_MODE=off app /usr/local/bin/composer dump-autoload

app@clean-cache:
	@docker exec -it -e XDEBUG_MODE=off wallet-nile-cron php cache.php
	@printf "Cache cleaned successfully.\n"

app@swole-version:
	@docker exec -it wallet-nile-cron php -r "echo swoole_version();"
	@printf "\nSwoole version displayed successfully."

## DOCKER
docker@tail-logs:
	@docker logs -f wallet-nile-cron

docker@restart:
	@docker restart wallet-nile-cron
	@printf "Docker wallet-nile environment successfully restarted.\n"


## COMMANDS
app@cron-sync-bet:
	@docker exec -it wallet-nile-cron php cron/syncbethistory.php
