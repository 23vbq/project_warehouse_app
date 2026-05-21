#!/bin/bash

echo "BUILD_ENV: "$BUILD_ENV

if [ "$BUILD_ENV" = "dev" ]; then
    composer install --no-cache -n
    php bin/console tailwind:build --watch &
fi

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear

docker-php-entrypoint apache2-foreground