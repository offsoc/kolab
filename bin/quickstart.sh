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

if [ "$1" == "--nodev" ]; then
    echo "Starting everything in containers"
    docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.build.yml build
    docker compose up -d --wait
    exit 0
fi
echo "Starting the development environment"


containers=(coturn mariadb meet pdns redis roundcube minio)

if grep -q "ldap" docker-compose.override.yml; then
    containers+=(ldap)
fi
# We grep for something that is unique to the container
if grep -q "kolab-init" docker-compose.override.yml; then
    containers+=(kolab)
fi
if grep -q "imap" docker-compose.override.yml; then
    containers+=(imap)
fi
if grep -q "postfix" docker-compose.override.yml; then
    containers+=(postfix)
fi
if grep -q "imap-frontend" docker-compose.override.yml; then
    containers+=(imap-frontend imap-backend imap-mupdate)
fi


docker compose build
docker compose up -d --wait ${containers[@]}

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
