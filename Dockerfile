# syntax=docker/dockerfile:1.7

FROM php:8.5-apache

ARG APP_USER
ARG APP_UID=1000
ARG APP_GID=1000

RUN test -n "$APP_USER" || (echo "APP_USER build arg is required" && exit 1)

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        libicu-dev \
        libzip-dev \
        libxml2-dev \
        libonig-dev \
        acl \
        ca-certificates \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        intl \
        zip \
        bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite headers

RUN groupadd -g ${APP_GID} ${APP_USER} \
    && useradd -u ${APP_UID} -g ${APP_USER} -m -d /home/${APP_USER} -s /bin/bash ${APP_USER}

ENV APACHE_RUN_USER=${APP_USER}
ENV APACHE_RUN_GROUP=${APP_USER}
ENV HOME=/home/${APP_USER}

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN sed -ri "s!/var/www/html!/home/${APP_USER}/public_html/public!g" /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /home/%s/public_html/public/>\n    AllowOverride All\n    Require all granted\n</Directory>\n' "${APP_USER}" >> /etc/apache2/apache2.conf

RUN printf '%s\n' \
        'date.timezone = Africa/Johannesburg' \
        'memory_limit = 512M' \
        'upload_max_filesize = 32M' \
        'post_max_size = 32M' \
        'expose_php = Off' \
        'display_errors = Off' \
        'log_errors = On' \
        'short_open_tag = Off' \
        'zend.assertions = -1' \
        'realpath_cache_size = 4096k' \
        'realpath_cache_ttl = 600' \
        'opcache.enable = 1' \
        'opcache.enable_cli = 0' \
        'opcache.memory_consumption = 256' \
        'opcache.max_accelerated_files = 20000' \
        'opcache.validate_timestamps = 0' \
        'opcache.interned_strings_buffer = 32' \
        'session.gc_maxlifetime = 28800' \
        'session.cookie_lifetime = 28800' \
        'session.cookie_httponly = 1' \
        'session.cookie_secure = 1' \
        'session.cookie_samesite = "Lax"' \
        'session.use_strict_mode = 1' \
        'session.use_only_cookies = 1' \
    > /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /home/${APP_USER}/public_html

RUN chown ${APP_USER}:${APP_USER} /home/${APP_USER}/public_html \
 && mkdir -p /home/${APP_USER}/public_html/var \
 && chown -R ${APP_USER}:${APP_USER} /home/${APP_USER}/public_html/var

# Composer dependencies — cached unless composer.json / composer.lock / symfony.lock change
COPY --chown=${APP_USER}:${APP_USER} composer.json composer.lock symfony.lock ./
USER ${APP_USER}
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
USER root

# Application source — busts on any code change (expected)
COPY --chown=${APP_USER}:${APP_USER} . /home/${APP_USER}/public_html

# Final build: autoloader, env cache
USER ${APP_USER}
RUN composer dump-autoload --optimize --no-dev \
    && composer dump-env prod \
    && rm -f .env.local
USER root

EXPOSE 80

CMD ["apache2-foreground"]
