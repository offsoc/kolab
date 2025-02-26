#!/bin/bash

if [ -d /src/kolabsrc.orig ]; then
    rsync -av \
        --exclude=vendor \
        --exclude=composer.lock \
        --exclude=node_modules \
        --exclude=package-lock.json \
        --exclude=public \
        --exclude=storage \
        --exclude=resources/build \
        --exclude=.gitignore \
        --exclude=.env \
        /src/kolabsrc.orig/ /opt/app-root/src/ | tee /tmp/rsync.output
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
        --exclude=.env \
        /src/overlay/ /opt/app-root/src/ | tee /tmp/rsync-overlay.output
fi

