FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-install intl opcache pdo_pgsql zip \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/entrypoint.sh /usr/local/bin/veille-entrypoint

WORKDIR /var/www/app

RUN chmod +x /usr/local/bin/veille-entrypoint \
    && chown -R www-data:www-data /var/www

ENTRYPOINT ["veille-entrypoint"]
CMD ["apache2-foreground"]
