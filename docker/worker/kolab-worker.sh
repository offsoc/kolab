#!/bin/bash

if [ -d /home/worker/src/ ]; then
    rm -rf /home/worker/src/
fi

cp -a /home/worker/src.orig/ /home/worker/src/
chown -R worker:worker /home/worker/src/

pushd /home/worker/src/

rm -rf bootstrap/cache/
mkdir -p bootstrap/cache/

./artisan queue:work
