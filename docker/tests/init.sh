#!/bin/bash
set -e
set -x

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
    /src/kolabsrc.orig/ /opt/app-root/src/ | tee /tmp/rsync.output

cd /opt/app-root/src/

if [ "$1" == "--refresh" ]; then
    rm -rf storage/framework
    mkdir -p storage/framework/{sessions,views,cache}

    find bootstrap/cache/ -type f ! -name ".gitignore" -delete
    ./artisan clear-compiled
    ./artisan cache:clear

    # FIXME seems to be required for db seed to function
    composer update

    if rpm -qv chromium 2>/dev/null; then
        chver=$(rpmquery --queryformat="%{VERSION}" chromium | awk -F'.' '{print $1}')
        ./artisan dusk:chrome-driver ${chver}
    fi

    # Only run npm if something relevant was updated
    if grep -e "package.json" -e "resources" /tmp/rsync.output; then
        npm run dev
    fi

    ./artisan db:ping --wait


    # Unconditionally get the database into shape
    php -dmemory_limit=512M ./artisan migrate:refresh --seed

    ./artisan data:import || :
    ./artisan queue:work --stop-when-empty

    shift
fi

./artisan route:list

EXCLUDE_GROUPS=${EXCLUDE_GROUPS:-"skipci,ldap,coinbase,mollie,stripe,meet,dns,slow"}

if [ "$1" == "suite-Functional" ]; then
    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci,ldap \
        --verbose \
        --testsuite Unit

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci,ldap \
        --verbose \
        --testsuite Functional
elif [ "$1" == "suite-Feature" ]; then
    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group "$EXCLUDE_GROUPS" \
        --verbose \
        --testsuite Feature
elif [ "$1" == "suite-Browser" ]; then

    # Can't do browser tests over https
    echo "APP_URL=http://kolab.local" >> .env
    echo "APP_PUBLIC_URL=http://kolab.local" >> .env
    echo "ASSET_URL=http://kolab.local" >> .env
    echo "MEET_SERVER_URLS=http://kolab.local/meetmedia/api/" >> .env
    echo "APP_HEADER_CSP=" >> .env
    echo "APP_HEADER_XFO=" >> .env

    ./artisan octane:start --port=80 >/dev/null 2>&1 &
    echo "127.0.0.1 kolab.local admin.kolab.local reseller.kolab.local" >> /etc/hosts

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group skipci,ldap,meet,mollie \
        --verbose \
        --testsuite Browser
elif [ "$1" == "testsuite" ]; then
    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group "$EXCLUDE_GROUPS" \
        --verbose \
        --testsuite Unit

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group "$EXCLUDE_GROUPS" \
        --verbose \
        --testsuite Functional

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group "$EXCLUDE_GROUPS" \
        --verbose \
        --testsuite Feature
elif [ "$1" == "quicktest" ]; then
    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group "$EXCLUDE_GROUPS" \
        --verbose \
        --stop-on-defect \
        --stop-on-error \
        --stop-on-failure \
        --testsuite Unit

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group "$EXCLUDE_GROUPS" \
        --verbose \
        --stop-on-defect \
        --stop-on-error \
        --stop-on-failure \
        --testsuite Functional

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group "$EXCLUDE_GROUPS" \
        --verbose \
        --stop-on-defect \
        --stop-on-error \
        --stop-on-failure \
        --testsuite Feature
elif [ "$1" == "shell" ]; then
    exec /bin/bash
elif [ "$1" == "lint" ]; then
    php -dmemory_limit=-1 vendor/bin/phpcs -p

    php -dmemory_limit=-1 vendor/bin/phpstan analyse

    npm run lint
else
    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group "$EXCLUDE_GROUPS" \
        --verbose \
        --stop-on-defect \
        --stop-on-error \
        --stop-on-failure \
        "$1"
fi
