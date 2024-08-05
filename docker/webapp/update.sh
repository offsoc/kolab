#!/bin/bash
set -e
set -x

/update-source.sh

cd /opt/app-root/src/

rm -rf storage/framework
mkdir -p storage/framework/{sessions,views,cache}

if grep -e "composer.json" -e "app" /tmp/rsync.output; then
    rm composer.lock
    # Must be before the first artisan command because those can fail otherwise)
    php -dmemory_limit=-1 $(command -v composer) install
fi

find bootstrap/cache/ -type f ! -name ".gitignore" -delete
./artisan clear-compiled
./artisan cache:clear

# Only run npm if something relevant was updated
if grep -e "package.json" -e "resources" /tmp/rsync.output; then
    npm run dev
fi
# Can fail if octane is not running
./artisan octane:reload || :
