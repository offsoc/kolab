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

base_dir=$(dirname $(dirname $0))

bin/regen-certs

docker pull kolab/centos7:latest

docker-compose down
docker-compose build

docker-compose up -d kolab mariadb redis

pushd ${base_dir}/src/
composer install
npm install
find bootstrap/cache/ -type f ! -name ".gitignore" -delete
cp .env.example .env
./artisan key:generate
./artisan jwt:secret -f
./artisan clear-compiled
./artisan cache:clear
npm run dev
popd

docker-compose up -d worker

pushd ${base_dir}/src/
rm -rf database/database.sqlite
php -dmemory_limit=512M ./artisan migrate:refresh --seed
./artisan serve
popd

