# INSTALL
start:
	@cd environment && docker-compose up -d

start-boot:
	@cd environment && docker-compose run --rm app composer install

build:
	@cd environment && docker-compose up --build -d

down:
	@cd environment && docker-compose down

app@install:
	@docker start app exec -it -e XDEBUG_MODE=off app /usr/local/bin/composer install
	@make app@restart
	@printf "\nApplication dependencies installed successfully.\n"

# DEV
app@restart:
	@make docker@app
	@printf 'Application restarted successfully.'

app@autoload:
	@docker exec -it -e XDEBUG_MODE=off app /usr/local/bin/composer dump-autoload

app@clean-cache:
	@docker exec -it -e XDEBUG_MODE=off app php cache.php
	@printf "Cache cleaned successfully.\n"

app@swole-version:
	@docker exec -it app php -r "echo swoole_version();"
	@printf "\nSwoole version displayed successfully."

## DOCKER
docker@tail-logs:
	@docker logs -f app
	@printf "Docker app logs are being followed..."

docker@restart-app:
	@docker restart app
	@docker exec -it -e XDEBUG_MODE=off app /usr/local/bin/composer dump-autoload
	@printf "Docker app environment successfully restarted.\n"

docker@restart-workers:
	 @docker restart app
	@docker exec -it -e XDEBUG_MODE=off wallet-nile-app /usr/local/bin/composer dump-autoload
	@printf "Docker worker environment successfully restarted.\n"

## COMMANDS
app@cron-sync-bet:
	@docker exec -it app php cron/syncbethistory.php
