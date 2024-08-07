#!/bin/bash
set -e
set -x

REBUILD=false
# Update the sourcecode if available.
# This also copies the .env files that is required if we don't provide
# a configuration via the environment.
# So we need this for the podman setup
if [ -d /src/kolabsrc.orig ]; then
    echo "----> Updating source"
    /update-source.sh

    REBUILD=true
fi

if [ -d /src/overlay ]; then
    echo "----> Applying overlay"
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
        /src/overlay/ /opt/app-root/src/ | tee /tmp/rsync-overlay.output

    REBUILD=true
fi

cd /opt/app-root/src/

if [[ $REBUILD == true ]]; then
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
fi
