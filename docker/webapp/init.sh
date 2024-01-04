#!/bin/bash
set -e
set -x

cd /opt/app-root/src/

# Update the sourcecode if available.
# This also copies the .env files that is required if we don't provide
# a configuration via the environment.
# So we need this for the docker-compose setup
if [ -d /src/kolabsrc.orig ]; then
    echo "----> Updating source"
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

    rm -rf storage/framework
    mkdir -p storage/framework/{sessions,views,cache}

    find bootstrap/cache/ -type f ! -name ".gitignore" -delete
    ./artisan clear-compiled
    ./artisan cache:clear

    php -dmemory_limit=-1 $(command -v composer) install
fi

if [ ! -f 'resources/countries.php' ]; then
    echo "----> Importing countries"
    ./artisan data:countries
fi

echo "----> Waiting for db"
./artisan db:ping --wait

case ${KOLAB_ROLE} in
    seed|SEED)
        # seed only if not seeded yet
        if [[ ! $(./artisan migrate:status > /dev/null) ]]; then
            echo "----> Seeding the database"
            php -dmemory_limit=512M ./artisan migrate --seed || :
        fi
    ;;

    horizon|HORIZON)

        echo "----> Waiting for database to be seeded"
        # migrate:status only fails if the migration table doesn't exist
        while [[ $(./artisan migrate:status > /dev/null) ]]; do
            sleep 1
            echo "."
        done

        echo "----> Running migrations"
        php -dmemory_limit=512M ./artisan migrate --force || :
        echo "----> Starting horizon"
        ./artisan horizon
    ;;

    octane|OCTANE)
        echo "----> Running octane"
        echo "----> Waiting for database to be seeded"
        # migrate:status only fails if the migration table doesn't exist
        while [[ $(./artisan migrate:status > /dev/null) ]]; do
            sleep 1
            echo "."
        done

        exec ./artisan octane:start --host=0.0.0.0
    ;;

    worker|WORKER )
        ./artisan migrate --force || :
        echo "----> Running worker"
        exec ./artisan queue:work
    ;;

    combined|COMBINED )
        # If there is no db at all then listing users will crash (resulting in us counting the lines of backtrace),
        # but migrate:status will just fail.
        if [[ ! $(./artisan migrate:status > /dev/null) ]]; then
            echo "----> Seeding the database"
            php -dmemory_limit=512M ./artisan migrate --seed || :
        # If there is a db but no user we reseed
        elif test "$( env APP_DEBUG=false ./artisan -n users | wc -l )" -lt "1"; then
            echo "----> Initializing the database"
            php -dmemory_limit=512M ./artisan migrate:refresh --seed
        # Otherwise we just migrate
        else
            echo "----> Running migrations"
            php -dmemory_limit=512M ./artisan migrate --force
        fi
        ./artisan data:import || :
        nohup ./artisan horizon 2>&1 &
        exec ./artisan octane:start --host=$(env | grep OCTANE_HTTP_HOST | tail -n1 | sed "s/OCTANE_HTTP_HOST=//")
    ;;

    * )
        echo "----> Sleeping"
        exec sleep 10000
    ;;
esac
