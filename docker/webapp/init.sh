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

if [ ! -f 'resources/countries.php' ]; then
    ./artisan data:countries
fi

/usr/local/bin/npm run dev

./artisan db:ping --wait
php -dmemory_limit=512M ./artisan migrate --force
if test "$( env APP_DEBUG=false ./artisan -n users | wc -l )" -lt "1"; then
    php -dmemory_limit=512M ./artisan db:seed
fi
./artisan data:import || :
nohup ./artisan horizon >/dev/null 2>&1 &
./artisan octane:start --host=$(grep OCTANE_HTTP_HOST .env | tail -n1 | sed "s/OCTANE_HTTP_HOST=//")
