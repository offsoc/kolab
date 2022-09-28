#!/bin/bash

set -e
set -x

src/artisan octane:stop >/dev/null 2>&1 || :
src/artisan horizon:terminate >/dev/null 2>&1 || :

# Always reset .env with .env.example
cp src/.env.example src/.env

if [ -f "src/env.local" ]; then
    # Ensure there's a line ending
    echo "" >> src/.env
    cat src/env.local >> src/.env
fi

src/artisan db:ping --wait
nohup src/artisan octane:start --host=$(grep OCTANE_HTTP_HOST .env | tail -n1 | sed "s/OCTANE_HTTP_HOST=//") > src/octane.out &
nohup src/artisan horizon > src/horizon.out &
