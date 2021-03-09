#!/bin/bash

export APP_DEBUG="true"
export APP_KEY=
export APP_PUBLIC_URL=http://127.0.0.1:8000/
export APP_SRC=src/
export APP_URL=http://127.0.0.1:8000/
export CACHE_DRIVER="array"
export COMPOSER_ARGS="--no-dev"
export DB_CONNECTION="sqlite"
export DB_DATABASE=":memory:"
export GIT_URI=https://git.kolab.org/source/kolab.git
export GIT_BRANCH=dev/pstimport
export LARAVEL_ENV=production
export LOG_CHANNEL="stderr"
export MAIL_DRIVER="array"
export QUEUE_CONNECTION="sync"
export SESSION_DRIVER="array"

docker build -t migrate .

docker kill migrate

docker rm migrate

docker_opts="\
    -e APP_DEBUG=${APP_DEBUG} \
    -e APP_KEY=${APP_KEY} \
    -e APP_PUBLIC_URL=${APP_PUBLIC_URL} \
    -e APP_SRC=${APP_SRC} \
    -e APP_URL=${APP_URL} \
    -e CACHE_DRIVER=${CACHE_DRIVER} \
    -e COMPOSER_ARGS=${COMPOSER_ARGS} \
    -e DB_CONNECTION=${DB_CONNECTION} \
    -e DB_DATABASE=${DB_DATABASE} \
    -e GIT_URI=${GIT_URI} \
    -e GIT_BRANCH=${GIT_BRANCH} \
    -e LARAVEL_ENV=${LARAVEL_ENV} \
    -e LOG_CHANNEL=${LOG_CHANNEL} \
    -e MAIL_DRIVER=${MAIL_DRIVER} \
    -e QUEUE_CONNECTION=${QUEUE_CONNECTION} \
    -e SESSION_DRIVER=${SESSION_DRIVER}"

docker run -it \
    ${docker_opts} \
    --name migrate migrate /usr/local/bin/build-image

docker commit migrate migrate-s2i
