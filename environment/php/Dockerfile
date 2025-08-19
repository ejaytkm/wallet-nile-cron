FROM arm64v8/php:8.3-fpm-alpine

# Update and install required dependencies
RUN apk update && apk add --no-cache \
    bash \
    build-base \
    gcc \
    git \
    autoconf \
    g++ \
    make \
    openssl-dev \
    libzip-dev \
    linux-headers \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    curl

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd pdo_mysql mysqli opcache zip && \
    pecl install redis xdebug && \
    docker-php-ext-enable redis opcache

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set up configuration files
ARG php_path='.'
COPY ${php_path}/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
COPY ${php_path}/php.ini /usr/local/etc/php/conf.d/php.ini
COPY ${php_path}/php-fpm.conf /usr/local/etc/php-fpm.d/

# Expose PHP-FPM port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]