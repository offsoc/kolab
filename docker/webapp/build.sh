#!/bin/bash
set -e
set -x

mkdir /src
cd /src

git clone https://git.kolab.org/source/kolab.git kolab
cp -a kolab/src /src/kolabsrc
cd /src/kolabsrc

mkdir -p storage/framework/{sessions,views,cache}
mkdir -p database/seeds

php -dmemory_limit=-1 $(command -v composer) install
npm -g install npm
/usr/local/bin/npm install
./artisan storage:link
./artisan clear-compiled
if [ ! -f 'resources/countries.php' ]; then
    ./artisan data:countries
fi

/usr/local/bin/npm run dev
