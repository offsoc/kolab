#!/bin/bash

if [ -d /home/worker/src/ ]; then
    rm -rf /home/worker/src/
fi

cp -a /home/worker/src.orig/ /home/worker/src/
mkdir -p /home/worker/src/storage/framework/{cache,sessions,views}
chown -R worker:worker /home/worker/src/

pushd /home/worker/src/

rm -rf bootstrap/cache/
mkdir -p bootstrap/cache/

./artisan db:ping --wait

./artisan queue:work
