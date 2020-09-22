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
export LARAVEL_ENV=production
export LOG_CHANNEL="stderr"
export MAIL_DRIVER="array"
export QUEUE_CONNECTION="sync"
export SESSION_DRIVER="array"
export SWOOLE_HTTP_HOST=0.0.0.0
export SWOOLE_HTTP_PORT=8000
export SWOOLE_HTTP_REACTOR_NUM=6
export SWOOLE_HTTP_WORKER_NUM=6

docker build -t swoole .

docker kill swoole

docker rm swoole

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
    -e LARAVEL_ENV=${LARAVEL_ENV} \
    -e LOG_CHANNEL=${LOG_CHANNEL} \
    -e MAIL_DRIVER=${MAIL_DRIVER} \
    -e QUEUE_CONNECTION=${QUEUE_CONNECTION} \
    -e SESSION_DRIVER=${SESSION_DRIVER} \
    -e SWOOLE_HTTP_HOST=${SWOOLE_HTTP_HOST} \
    -e SWOOLE_HTTP_PORT=${SWOOLE_HTTP_PORT} \
    -e SWOOLE_HTTP_REACTOR_NUM=${SWOOLE_HTTP_REACTOR_NUM} \
    -e SWOOLE_HTTP_WORKER_NUM=${SWOOLE_HTTP_WORKER_NUM}"

docker run -it \
    ${docker_opts} \
    --name swoole swoole /usr/local/bin/build-image

docker commit swoole swoole-s2i

docker kill swoole-s2i

docker rm swoole-s2i

docker run -it -p 8000:8000 \
    ${docker_opts} \
    --name swoole-s2i swoole-s2i /usr/local/bin/run-container
