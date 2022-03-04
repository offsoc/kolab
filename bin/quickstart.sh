#!/bin/bash

set -e

function die() {
    echo "$1"
    exit 1
}

rpm -qv composer >/dev/null 2>&1 || \
    test ! -z "$(which composer 2>/dev/null)" || \
    die "Is composer installed?"

rpm -qv docker-compose >/dev/null 2>&1 || \
    test ! -z "$(which docker-compose 2>/dev/null)" || \
    die "Is docker-compose installed?"

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

test ! -z "$(grep 'systemd.unified_cgroup_hierarchy=0' /proc/cmdline)" || \
    die "systemd containers only work with cgroupv1 (use 'grubby --update-kernel=ALL --args=\"systemd.unified_cgroup_hierarchy=0\"' and a reboot to fix)"

base_dir=$(dirname $(dirname $0))

# Always reset .env with .env.example
cp src/.env.example src/.env

if [ -f "src/env.local" ]; then
    # Ensure there's a line ending
    echo "" >> src/.env
    cat src/env.local >> src/.env
fi

docker pull docker.io/kolab/centos7:latest

docker-compose down --remove-orphans
docker-compose build coturn kolab mariadb meet pdns-sql proxy redis nginx

bin/regen-certs

docker-compose up -d coturn kolab mariadb meet pdns-sql proxy redis

# Workaround until we have docker-compose --wait (https://github.com/docker/compose/pull/8777)
function wait_for_container {
    container_id="$1"
    container_name="$(docker inspect "${container_id}" --format '{{ .Name }}')"
    echo "Waiting for container: ${container_name} [${container_id}]"
    waiting_done="false"
    while [[ "${waiting_done}" != "true" ]]; do
        container_state="$(docker inspect "${container_id}" --format '{{ .State.Status }}')"
        if [[ "${container_state}" == "running" ]]; then
            health_status="$(docker inspect "${container_id}" --format '{{ .State.Health.Status }}')"
            echo "${container_name}: container_state=${container_state}, health_status=${health_status}"
            if [[ ${health_status} == "healthy" ]]; then
                waiting_done="true"
            fi
        else
            echo "${container_name}: container_state=${container_state}"
            waiting_done="true"
        fi
        sleep 1;
    done;
}

# Ensure the containers we depend on are fully started
wait_for_container 'kolab'
wait_for_container 'kolab-redis'

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


if rpm -qv chromium 2>/dev/null; then
    chver=$(rpmquery --queryformat="%{VERSION}" chromium | awk -F'.' '{print $1}')
    ./artisan dusk:chrome-driver ${chver}
fi

if [ ! -f 'resources/countries.php' ]; then
    ./artisan data:countries
fi

npm run dev
popd

docker-compose up -d nginx

pushd ${base_dir}/src/
rm -rf database/database.sqlite
./artisan db:ping --wait
php -dmemory_limit=512M ./artisan migrate:refresh --seed
./artisan data:import || :
./artisan octane:stop >/dev/null 2>&1 || :
nohup ./artisan octane:start --host=$(grep OCTANE_HTTP_HOST .env | tail -n1 | sed "s/OCTANE_HTTP_HOST=//") > octane.out &
./artisan horizon:terminate >/dev/null 2>&1 || :
nohup ./artisan horizon > horizon.out &
popd
