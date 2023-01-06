#!/bin/bash
#set -e
rm -rf /src/kolabsrc
sudo cp -a /src/kolabsrc.orig /src/kolabsrc
sudo chmod 777 -R /src/kolabsrc
cd /src/kolabsrc

sudo rm -rf vendor/ composer.lock
php -dmemory_limit=-1 $(command -v composer) install
sudo rm -rf node_modules
mkdir node_modules
npm install
find bootstrap/cache/ -type f ! -name ".gitignore" -delete
./artisan storage:link
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

if [ "$1" == "testsuite" ]; then
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
