#!/bin/bash

set -e
set -x

function die() {
    echo "$1"
    exit 1
}

test ! -z "$(grep 'systemd.unified_cgroup_hierarchy=0' /proc/cmdline)" || \
    die "systemd containers only work with cgroupv1 (use 'grubby --update-kernel=ALL --args=\"systemd.unified_cgroup_hierarchy=0\"' and a reboot to fix)"

base_dir=$(dirname $(dirname $0))



export DOCKER_BUILDKIT=1
export BUILDKIT_PROGRESS=plain

docker compose down -t 1 --remove-orphans
volumes=($(docker volume ls -f name=kolab | awk '{if (NR > 1) print $2}'))
for v in "${volumes[@]}"
do
    docker volume rm $v || :
done

# We can't use the following artisan commands because it will just block if redis is unavailable:
# src/artisan octane:stop >/dev/null 2>&1 || :
# src/artisan horizon:terminate >/dev/null 2>&1 || :
# we therefore just kill all artisan processes running.
pkill -9 -f artisan || :
pkill -9 -f swoole || :

bin/regen-certs

docker compose build

# Build the murder setup if configured
if grep -q "imap-frontend" docker-compose.override.yml; then
    docker compose build imap-frontend imap-backend imap-mupdate
fi
if grep -q "ldap" docker-compose.override.yml; then
    docker compose up -d ldap
fi
# We grep for something that is unique to the container
if grep -q "kolab-init" docker-compose.override.yml; then
    docker compose up -d kolab
fi
if grep -q "imap" docker-compose.override.yml; then
    docker compose up -d imap
fi
if grep -q "postfix" docker-compose.override.yml; then
    docker compose up -d postfix
fi
if grep -q "imap-frontend" docker-compose.override.yml; then
    docker compose up -d imap-frontend imap-backend imap-mupdate
fi

docker compose up -d coturn mariadb meet pdns redis roundcube minio

if [ "$1" == "--nodev" ]; then
    echo "starting everything in containers"
    docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.build.yml build swoole webapp
    docker compose up -d --wait webapp
    if grep -q "haproxy" docker-compose.override.yml; then
        docker compose up --no-deps -d haproxy
    fi
    docker compose up --no-deps -d proxy
    exit 0
fi
echo "Starting the development environment"

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

# We grep for something that is unique to the container
if grep -q "kolab-init" docker-compose.override.yml; then
    docker compose up --no-recreate --wait kolab
fi
docker compose up --no-recreate --wait redis


pushd ${base_dir}/src/

rm -rf vendor/ composer.lock
php -dmemory_limit=-1 $(which composer) install
npm install
find bootstrap/cache/ -type f ! -name ".gitignore" -delete
./artisan key:generate
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
popd

pushd ${base_dir}/src/
rm -rf database/database.sqlite
./artisan db:ping --wait
php -dmemory_limit=512M ./artisan migrate:refresh --seed
./artisan data:import || :
nohup ./artisan octane:start --host=$(grep OCTANE_HTTP_HOST .env | tail -n1 | sed "s/OCTANE_HTTP_HOST=//") > octane.out &
nohup ./artisan horizon > horizon.out &

popd

if grep -q "haproxy" docker-compose.override.yml; then
    docker compose up --no-deps -d haproxy
fi
docker compose up --no-deps -d proxy
