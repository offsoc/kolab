#!/bin/bash
set -e
set -x

cd /opt/app-root/src/

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
    --exclude=.env \
    /src/overlay/ /opt/app-root/src/ | tee /tmp/rsync-overlay.output

# We rely on the environment for configuration
# We have to do this before running composer, because that attempts to read the .env file too.
rm -f .env


rm -rf storage/framework
mkdir -p storage/framework/{sessions,views,cache}

if grep -e "composer.json" -e "app" /tmp/rsync.output; then
    rm composer.lock
    # Must be before the first artisan command because those can fail otherwise)
    php -dmemory_limit=-1 $(command -v composer) install
fi

find bootstrap/cache/ -type f ! -name ".gitignore" -delete
./artisan clear-compiled
./artisan cache:clear || :

exec $@
