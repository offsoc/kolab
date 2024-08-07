#!/bin/bash
set -e
set -x

cd /opt/app-root/src/

/update.sh

# If we want to rely on the environment for configuration
if [[ $NOENVFILE == true ]]; then
    echo "----> removing envfile"
    rm -f .env
fi

if [ ! -f 'resources/countries.php' ]; then
    echo "----> Importing countries"
    ./artisan data:countries
fi

echo "----> Waiting for db"
./artisan db:ping --wait

# Import the service ca on openshift
update-ca-trust

function is_not_initialized() {
    ROWCOUNT=$(echo "select count(*) from migrations;" | mysql -N -b -u "$DB_USERNAME" -p"$DB_PASSWORD" -h "$DB_HOST" "$DB_DATABASE")
    if [[ "$ROWCOUNT" == "" ]]; then
        # Treat an error in the above command as uninitialized
        ROWCOUNT="0"
    fi
    [[ "$ROWCOUNT" == "0" ]]
}

case ${KOLAB_ROLE} in
    seed|SEED)
        echo "----> Running seeder"
        # Only run the seeder if we haven't even migrated yet.
        if is_not_initialized; then
            echo "----> Seeding the database"
            # If we seed, we always drop all existing tables
            php -dmemory_limit=512M ./artisan migrate:fresh --seed --force
        fi
    ;;

    horizon|HORIZON)

        echo "----> Waiting for database to be seeded"
        while is_not_initialized; do
            sleep 1
            echo "."
        done

        echo "----> Running migrations"
        php -dmemory_limit=512M ./artisan migrate --force || :
        echo "----> Starting horizon"
        exec ./artisan horizon
    ;;

    octane|OCTANE)
        echo "----> Running octane"
        echo "----> Waiting for database to be seeded"
        while is_not_initialized; do
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
        if is_not_initialized; then
            echo "----> Seeding the database"
            php -dmemory_limit=512M ./artisan migrate --seed
        # If there is a db but no user we reseed
        elif test "$( env APP_DEBUG=false ./artisan -n users | wc -l )" -lt "1"; then
            echo "----> Initializing the database"
            php -dmemory_limit=512M ./artisan migrate:refresh --seed
        # Otherwise we just migrate
        else
            echo "----> Running migrations"
            php -dmemory_limit=512M ./artisan migrate --force
        fi
        nohup ./artisan horizon 2>&1 &
        exec ./artisan octane:start --host=$(env | grep OCTANE_HTTP_HOST | tail -n1 | sed "s/OCTANE_HTTP_HOST=//")
    ;;

    * )
        echo "----> Sleeping"
        exec sleep 10000
    ;;
esac
