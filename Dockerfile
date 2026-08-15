FROM composer:2 AS vendor

WORKDIR /build
COPY app/composer.json app/composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader \
    --ignore-platform-req=ext-gd


FROM dunglas/frankenphp AS runtime

ENV SERVER_NAME=:80
ENV DB_PATH=/data/app.sqlite

RUN install-php-extensions gd opcache \
    && apt-get update \
    && apt-get install -y --no-install-recommends cron util-linux tzdata \
    && rm -rf /var/lib/apt/lists/*

COPY php.ini "$PHP_INI_DIR/conf.d/app.ini"

WORKDIR /app
COPY app/ /app/
COPY --from=vendor /build/vendor /app/vendor

COPY docker/crontab /etc/cron.d/scheduler
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod 0644 /etc/cron.d/scheduler \
    && chmod +x /usr/local/bin/app-entrypoint \
    && mkdir -p /data /app/public/storage/images

ENTRYPOINT ["app-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
