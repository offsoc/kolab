#!/bin/bash

set -e

function die() {
    echo "$1"
    exit 1
}

rpm -qv composer >/dev/null 2>&1 || \
    test ! -z "$(which composer 2>/dev/null)" || \
    die "Is composer installed?"

rpm -qv docker-compose >/dev/null 2>&1 || \
    test ! -z "$(which docker-compose 2>/dev/null)" || \
    die "Is docker-compose installed?"

rpm -qv npm >/dev/null 2>&1 || \
    test ! -z "$(which npm 2>/dev/null)" || \
    die "Is npm installed?"

rpm -qv php >/dev/null 2>&1 || \
    test ! -z "$(which php 2>/dev/null)" || \
    die "Is php installed?"

rpm -qv php-ldap >/dev/null 2>&1 || \
    test ! -z "$(php --ini | grep ldap)" || \
    die "Is php-ldap installed?"

rpm -qv php-mysqlnd >/dev/null 2>&1 || \
    test ! -z "$(php --ini | grep mysql)" || \
    die "Is php-mysqlnd installed?"

test ! -z "$(php --modules | grep swoole)" || \
    die "Is swoole installed?"

base_dir=$(dirname $(dirname $0))

docker pull docker.io/kolab/centos7:latest

docker-compose down --remove-orphans
docker-compose build

pushd ${base_dir}/src/

if [ ! -f ".env" ]; then
    cp .env.example .env
fi

if [ -f ".env.local" ]; then
    # Ensure there's a line ending
    echo "" >> .env
    cat .env.local >> .env
fi

popd

bin/regen-certs

docker-compose up -d coturn kolab mariadb openvidu kurento-media-server proxy redis

pushd ${base_dir}/src/

rm -rf vendor/ composer.lock
php -dmemory_limit=-1 /bin/composer install
npm install
find bootstrap/cache/ -type f ! -name ".gitignore" -delete
./artisan key:generate
./artisan clear-compiled
./artisan cache:clear
./artisan horizon:install
./artisan passport:keys --force


if [ ! -z "$(rpm -qv chromium 2>/dev/null)" ]; then
    chver=$(rpmquery --queryformat="%{VERSION}" chromium | awk -F'.' '{print $1}')
    ./artisan dusk:chrome-driver ${chver}
fi

if [ ! -f 'resources/countries.php' ]; then
    ./artisan data:countries
fi

npm run dev
popd

docker-compose up -d worker

pushd ${base_dir}/src/
rm -rf database/database.sqlite
./artisan db:ping --wait
php -dmemory_limit=512M ./artisan migrate:refresh --seed
./artisan swoole:http start
popd

