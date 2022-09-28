#!/bin/bash

set -e
set -x

function die() {
    echo "$1"
    exit 1
}

base_dir=$(dirname $(dirname $0))

src/artisan octane:stop >/dev/null 2>&1 || :
src/artisan horizon:terminate >/dev/null 2>&1 || :

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

src/artisan db:ping --wait
php -dmemory_limit=512M src/artisan migrate:refresh --seed
src/artisan data:import || :
