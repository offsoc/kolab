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
    --exclude=.gitignore \
    /src/kolabsrc.orig/ /opt/app-root/src/ | tee /tmp/rsync.output

cd /opt/app-root/src/

if [ "$1" == "--refresh" ]; then
    /update.sh
    shift
fi

./artisan route:list

EXCLUDE_GROUPS=${EXCLUDE_GROUPS:-"skipci,ldap,coinbase,mollie,stripe,meet,dns,slow"}

if [ "$1" == "browsertest" ]; then
    ./artisan octane:start --port=80 &
    echo "127.0.0.1 kolab.local admin.kolab.local reseller.kolab.local" >> /etc/hosts

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group "$EXCLUDE_GROUPS" \
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

    if [[ "$1" =~ "Browser" ]]; then
        echo "Assuming a browsertest and starting octane"
        ./artisan octane:start --port=80 &
        echo "127.0.0.1 kolab.local admin.kolab.local reseller.kolab.local" >> /etc/hosts
    fi

    php \
        -dmemory_limit=-1 \
        vendor/bin/phpunit \
        --exclude-group "$EXCLUDE_GROUPS" \
        --verbose \
        --stop-on-defect \
        --stop-on-error \
        --stop-on-failure \
        $@
fi
