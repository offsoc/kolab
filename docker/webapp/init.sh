#!/bin/bash
set -e
rm -rf /src/kolabsrc
cp -a /src/kolabsrc.orig /src/kolabsrc
cd /src/kolabsrc

rm -rf vendor/ composer.lock .npm storage/framework
mkdir -p storage/framework/{sessions,views,cache}

php -dmemory_limit=-1 $(command -v composer) install
npm install
find bootstrap/cache/ -type f ! -name ".gitignore" -delete
./artisan key:generate
./artisan storage:link
./artisan clear-compiled
./artisan cache:clear
./artisan horizon:install

if [ ! -f 'resources/countries.php' ]; then
    ./artisan data:countries
fi

npm run dev

./artisan db:ping --wait
php -dmemory_limit=512M ./artisan migrate:refresh --seed
./artisan data:import || :
nohup ./artisan horizon >/dev/null 2>&1 &
./artisan octane:start --host=$(grep OCTANE_HTTP_HOST .env | tail -n1 | sed "s/OCTANE_HTTP_HOST=//")
