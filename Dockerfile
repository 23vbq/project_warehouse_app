FROM php:8.4-apache

ARG BUILD_ENV

# Enable mod_rewrite for Symfony routing
RUN a2enmod rewrite

# PHP extensions required by Symfony + MySQL
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libonig-dev libzip-dev libpq-dev

RUN docker-php-ext-install mbstring pdo pdo_mysql intl zip calendar
RUN docker-php-ext-enable mbstring pdo pdo_mysql intl zip calendar

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Timezone
RUN ln -snf /usr/share/zoneinfo/Europe/Warsaw /etc/localtime && \
    echo "Europe/Warsaw" > /etc/timezone

WORKDIR /var/www/html

COPY . /var/www/html/
COPY apache.conf /etc/apache2/sites-available/000-default.conf

RUN if [ "$BUILD_ENV" != "dev" ]; then \
        composer install --no-interaction --no-scripts --optimize-autoloader --no-dev && \
        APP_ENV=prod php bin/console tailwind:build; \
    fi

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
