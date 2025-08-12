FROM php:8.3-cli-alpine

# Build/runtime deps for Swoole (with cURL hooks) + helpers
RUN apk add --no-cache \
      $PHPIZE_DEPS \
      curl-dev \
      openssl-dev \
      nghttp2-dev \
      pcre2-dev \
      zlib-dev \
      linux-headers \
      ca-certificates \
      bash \
      git \
      unzip \
      supervisor \
    && update-ca-certificates \
    && docker-php-ext-install pdo_mysql \
    && pecl install swoole xdebug \
    && docker-php-ext-enable swoole xdebug \
    && docker-php-ext-install opcache \
    && echo "opcache.enable_cli=1" > /usr/local/etc/php/conf.d/opcache-cli.ini \
    && echo "memory_limit=1024M" > /usr/local/etc/php/conf.d/memory.ini \
    # Install Composer (latest stable)
    && curl -sS https://getcomposer.org/installer | php -- \
         --install-dir=/usr/local/bin \
         --filename=composer \
    && composer --version

ARG php_path='.'
COPY ${php_path}/php.ini /usr/local/etc/php/conf.d/php.ini
COPY ${php_path}/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
# FIXED destination path here (was split as /usr/lo cal/...)
COPY ${php_path}/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Default command (overridden by docker-compose)
CMD ["php", "-v"]