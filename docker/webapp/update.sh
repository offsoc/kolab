#!/bin/bash
set -e
set -x

/update-source.sh

cd /opt/app-root/src/

rm -rf storage/framework
mkdir -p storage/framework/{sessions,views,cache}

find bootstrap/cache/ -type f ! -name ".gitignore" -delete
./artisan clear-compiled
./artisan cache:clear

if grep -e "composer.json" -e "app" /tmp/rsync.output; then
    php -dmemory_limit=-1 $(command -v composer) update
fi

# Only run npm if something relevant was updated
if grep -e "package.json" -e "resources" /tmp/rsync.output; then
    npm run dev
fi
./artisan octane:reload
