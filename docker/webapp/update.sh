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

cd /src/kolabsrc/
# Only run npm if something relevant was updated
if grep -e "package.json" -e "resources" /tmp/rsync.output; then
    npm run dev
fi
./artisan octane:reload
