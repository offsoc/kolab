#!/bin/bash
set -e
set -x

rsync -av \
    --exclude=vendor \
    --exclude=composer.lock \
    --exclude=node_modules \
    --exclude=package-lock.json \
    --exclude=.gitignore \
    /src/meet/ /src/meetsrc/ | tee /tmp/rsync.output
