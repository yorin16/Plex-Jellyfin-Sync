FROM php:8.4-cli-bookworm

# Add non-free repo for intel-media-va-driver-non-free (required for 6th gen+ iGPU)
RUN echo "deb http://deb.debian.org/debian bookworm contrib non-free non-free-firmware" \
        > /etc/apt/sources.list.d/non-free.list

# ffmpeg on Debian Bookworm is compiled with --enable-libmfx which uses Intel
# Media SDK; libmfx1 provides the runtime and intel-media-va-driver-non-free
# provides the iHD driver that backs it — this chain works for hevc_qsv on
# Intel 6th gen and newer (including 12th/13th/14th gen Raptor Lake).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev rsync openssh-client sshpass \
        ffmpeg libmfx1 intel-media-va-driver-non-free \
    && docker-php-ext-install pdo pdo_mysql zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY backend/ .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && mkdir -p var/cache var/log \
    && chmod -R 777 var/

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "bin/console", "messenger:consume", "transcode", "--time-limit=3600", "-vv"]
