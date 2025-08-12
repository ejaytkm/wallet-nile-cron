start:
	@cd environment && docker-compose up -d

build:
	@cd environment && docker-compose up --build -d

app@install:
	@docker exec -it -e XDEBUG_MODE=off dispatcher /usr/local/bin/composer install
	@printf "Application dependencies installed successfully.\n"

app@restart:
	@make docker@restart-dispatcher
	@make docker@restart-workers
	@printf 'Application restarted successfully.'

app@autoload:
	@docker exec -it -e XDEBUG_MODE=off dispatcher /usr/local/bin/composer dump-autoload


app@clean-cache:
	@docker exec -it -e XDEBUG_MODE=off dispatcher php cache.php
	@printf "Cache cleaned successfully.\n"

## DOCKER
docker@tail-logs:
	@docker logs -f dispatcher
	@printf "Docker dispatcher logs are being followed..."

docker@restart-dispatcher:
	@docker restart dispatcher
	@docker exec -it -e XDEBUG_MODE=off dispatcher /usr/local/bin/composer dump-autoload
	@printf "Docker dispatcher environment successfully restarted.\n"

docker@restart-workers:
	 @docker restart swoole-worker-1
	@docker exec -it -e XDEBUG_MODE=off swoole-worker-1 /usr/local/bin/composer dump-autoload
	@printf "Docker worker environment successfully restarted.\n"

## COMMANDS
app@dispatch-sync-bet:
	@docker exec -it dispatcher php dispatcher.php
