#!/bin/bash

echo "BUILD_ENV: "$BUILD_ENV

if [ "$BUILD_ENV" = "dev" ]; then
    composer install --no-cache -n --workdir=/var/www/html
    cd /var/www/html && (yarn install) && (yarn watch&)
else
    composer install --no-cache -n --optimize-autoloader --no-dev --workdir=/var/www/html
    cd /var/www/html && (yarn install) && (yarn build)
fi

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear

docker-php-entrypoint apache2-foreground