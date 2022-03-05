#!/bin/bash
set -e
cp -a /src/kolabsrc.orig /src/kolabsrc
cd /src/kolabsrc

rm -rf vendor/ composer.lock
php -dmemory_limit=-1 $(command -v composer) install
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

if rpm -qv chromium 2>/dev/null; then
    chver=$(rpmquery --queryformat="%{VERSION}" chromium | awk -F'.' '{print $1}')
    ./artisan dusk:chrome-driver ${chver}
fi

if [ ! -f 'resources/countries.php' ]; then
    ./artisan data:countries
fi

npm run dev




# Tests\Feature\Controller\PaymentsMollieEuroTest
# /usr/bin/chromium-browser --no-sandbox --headless --disable-gpu --remote-debugging-port=9222 http://localhost &

rm -rf database/database.sqlite
./artisan db:ping --wait
php -dmemory_limit=512M ./artisan migrate:refresh --seed
./artisan data:import || :
# nohup ./artisan horizon >/dev/null 2>&1 &
./artisan octane:start --host=$(grep OCTANE_HTTP_HOST .env | tail -n1 | sed "s/OCTANE_HTTP_HOST=//") >/dev/null 2>&1 &

# phpunit --verbose tests/Feature/Controller/PaymentsMollieEuroTest.php
php \
    -dmemory_limit=-1 \
    vendor/bin/phpunit \
    --verbose \
    --stop-on-defect \
    --stop-on-error \
    --stop-on-failure \
    --testsuite Unit

php \
    -dmemory_limit=-1 \
    vendor/bin/phpunit \
    --verbose \
    --stop-on-defect \
    --stop-on-error \
    --stop-on-failure \
    --testsuite Functional

php \
    -dmemory_limit=-1 \
    vendor/bin/phpunit \
    --verbose \
    --stop-on-defect \
    --stop-on-error \
    --stop-on-failure \
    --testsuite Feature
