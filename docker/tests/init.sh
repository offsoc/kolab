#!/bin/bash
#set -e
rsync -av \
    --exclude=vendor \
    --exclude=composer.lock \
    --exclude=node_modules \
    --exclude=package-lock.json \
    --exclude=public \
    --exclude=storage \
    --exclude=resources/build \
    --exclude=bootstrap \
    --exclude=.gitignore \
    /src/kolabsrc.orig/ /src/kolabsrc/ | tee /tmp/rsync.output
cd /src/kolabsrc

rm -rf storage/framework
mkdir -p storage/framework/{sessions,views,cache}

php -dmemory_limit=-1 $(command -v composer) update
/usr/local/bin/npm install
find bootstrap/cache/ -type f ! -name ".gitignore" -delete
./artisan clear-compiled
./artisan cache:clear
./artisan horizon:install

if rpm -qv chromium 2>/dev/null; then
    chver=$(rpmquery --queryformat="%{VERSION}" chromium | awk -F'.' '{print $1}')
    ./artisan dusk:chrome-driver ${chver}
fi

if [ ! -f 'resources/countries.php' ]; then
    ./artisan data:countries
fi

npm run dev

# /usr/bin/chromium-browser --no-sandbox --headless --disable-gpu --remote-debugging-port=9222 http://localhost &

rm -rf database/database.sqlite
./artisan db:ping --wait
php -dmemory_limit=512M ./artisan migrate --force
if test "$( env APP_DEBUG=false ./artisan -n users | wc -l )" -lt "1"; then
    php -dmemory_limit=512M ./artisan db:seed
fi
./artisan data:import || :
./artisan queue:work --stop-when-empty
./artisan octane:start --host=$(grep OCTANE_HTTP_HOST .env | tail -n1 | sed "s/OCTANE_HTTP_HOST=//") >/dev/null 2>&1 &



if [ "$1" == "suite-Functional" ]; then
    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci \
        --verbose \
        --testsuite Unit

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci \
        --verbose \
        --testsuite Functional
fi
if [ "$1" == "suite-Feature" ]; then
    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci,coinbase,mollie,stripe,meet,dns \
        --verbose \
        --testsuite Feature
fi
if [ "$1" == "suite-Browser" ]; then
    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci \
        --verbose \
        --testsuite Browser
fi
if [ "$1" == "testsuite" ]; then
    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci \
        --verbose \
        --testsuite Unit

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci \
        --verbose \
        --testsuite Functional

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci,coinbase,mollie,stripe,meet,dns \
        --verbose \
        --testsuite Feature
fi
if [ "$1" == "quicktest" ]; then
    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci \
        --verbose \
        --stop-on-defect \
        --stop-on-error \
        --stop-on-failure \
        --testsuite Unit

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci \
        --verbose \
        --stop-on-defect \
        --stop-on-error \
        --stop-on-failure \
        --testsuite Functional

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci,coinbase,mollie,stripe,meet,dns \
        --verbose \
        --stop-on-defect \
        --stop-on-error \
        --stop-on-failure \
        --testsuite Feature
fi
if [ "$1" == "shell" ]; then
    exec /bin/bash
fi
