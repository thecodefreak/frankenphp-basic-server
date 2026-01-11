FROM dunglas/frankenphp

#ENV SERVER=domain.com
ENV SERVER_NAME=:80

COPY ./php.ini "$PHP_INI_DIR/php.ini"

COPY ./app/public /app/public