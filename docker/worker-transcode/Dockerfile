FROM php:8.4-cli-bookworm

# php:*-bookworm uses DEB822-format sources without non-free.
# Replace with classic format so intel-media-va-driver-non-free is reachable.
RUN rm -f /etc/apt/sources.list.d/debian.sources \
    && echo 'deb http://deb.debian.org/debian bookworm main contrib non-free non-free-firmware' > /etc/apt/sources.list \
    && echo 'deb http://deb.debian.org/debian bookworm-updates main contrib non-free non-free-firmware' >> /etc/apt/sources.list \
    && echo 'deb http://deb.debian.org/debian-security bookworm-security main contrib non-free' >> /etc/apt/sources.list

# ffmpeg on Debian Bookworm is compiled with --enable-libmfx (Intel Media SDK).
# libmfx1 provides the MFX runtime; intel-media-va-driver-non-free provides
# the iHD VA-API driver that backs it for 6th gen+ iGPU (including 14th gen).
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
