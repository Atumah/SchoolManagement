FROM php:8.2-apache

ARG USER_ID=1000
ARG GROUP_ID=1000

ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libzip-dev \
        unzip \
        curl \
        mariadb-client \
    && docker-php-ext-install \
        intl \
        pdo_mysql \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/project.ini

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/default-ssl.conf

WORKDIR /var/www/html

RUN groupmod -o -g ${GROUP_ID} www-data \
    && usermod -o -u ${USER_ID} www-data

USER www-data

