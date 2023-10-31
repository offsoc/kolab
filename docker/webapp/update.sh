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
    /src/kolabsrc.orig/ /opt/app-root/src/ | tee /tmp/rsync.output

cd /opt/app-root/src/

rm -rf storage/framework
mkdir -p storage/framework/{sessions,views,cache}

find bootstrap/cache/ -type f ! -name ".gitignore" -delete
./artisan clear-compiled
./artisan cache:clear

php -dmemory_limit=-1 $(command -v composer) update

# Only run npm if something relevant was updated
if grep -e "package.json" -e "resources" /tmp/rsync.output; then
    npm run dev
fi
./artisan octane:reload
