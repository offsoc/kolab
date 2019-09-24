#!/bin/bash

if [ -d /home/worker/src/ ]; then
    rm -rf /home/worker/src/
fi

cp -a /home/worker/src.orig/ /home/worker/src/

pushd /home/worker/src/

./artisan queue:work
