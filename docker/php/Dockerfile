FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    rsync \
    openssh-client \
    sshpass \
    ffmpeg \
    libva \
    intel-media-driver \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy backend source and install dependencies
COPY backend/ .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && mkdir -p var/cache var/log \
    && chmod -R 777 var/

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
