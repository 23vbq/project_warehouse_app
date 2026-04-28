#!/bin/bash

echo "BUILD_ENV: "$BUILD_ENV

if [ "$BUILD_ENV" = "dev" ]; then
    composer install --no-cache -n --workdir=/var/www/html
    php bin/console tailwind:build --watch &
else
    composer install --no-cache -n --optimize-autoloader --no-dev --workdir=/var/www/html
fi

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear

docker-php-entrypoint apache2-foreground