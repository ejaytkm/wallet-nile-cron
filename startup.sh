#!/bin/bash

[ ! -d "vendor" ] && composer install || true

exec php /var/app/current/server.php