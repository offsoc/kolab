#!/bin/bash

set -e

function die() {
    echo "$1"
    exit 1
}

base_dir=$(dirname $(dirname $0))

# Always reset .env with .env.example
cp src/.env.example src/.env

if [ -f "src/env.local" ]; then
    # Ensure there's a line ending
    echo "" >> src/.env
    cat src/env.local >> src/.env
fi

rpm -qv composer >/dev/null 2>&1 || \
    test ! -z "$(which composer 2>/dev/null)" || \
    die "Is composer installed?"

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

pushd ${base_dir}/src/

rm -rf vendor/ composer.lock
php -dmemory_limit=-1 $(which composer) install
npm install
find bootstrap/cache/ -type f ! -name ".gitignore" -delete
./artisan key:generate
./artisan clear-compiled
./artisan cache:clear
./artisan horizon:install

if [ ! -f storage/oauth-public.key -o ! -f storage/oauth-private.key ]; then
    ./artisan passport:keys --force
fi

cat >> .env << EOF
PASSPORT_PRIVATE_KEY="$(cat storage/oauth-private.key)"
PASSPORT_PUBLIC_KEY="$(cat storage/oauth-public.key)"
EOF

if [ ! -f 'resources/countries.php' ]; then
    ./artisan data:countries
fi

npm run dev
popd

pushd ${base_dir}/src/
./artisan db:ping --wait
php -dmemory_limit=512M ./artisan migrate:refresh --seed
./artisan data:import || :
nohup ./artisan octane:start --host=$(grep OCTANE_HTTP_HOST .env | tail -n1 | sed "s/OCTANE_HTTP_HOST=//") > octane.out &
nohup ./artisan horizon > horizon.out &
popd
