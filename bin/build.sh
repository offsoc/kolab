#!/bin/bash

set -e

podman__build() {
    path=$1
    shift
    name=$1
    shift
    if [[ "$CACHE_REGISTRY" != "" ]]; then
        CACHE_ARGS="--layers --cache-from=$CACHE_REGISTRY/$name --cache-to=$CACHE_REGISTRY/$name --cache-ttl=24h"
    fi
    podman build $@ $CACHE_ARGS $path -t $name
}

podman__build_base() {
    podman__build docker/base/ apheleia/almalinux9 -f almalinux9
    podman__build docker/swoole apheleia/swoole
}

podman__build_webapp() {
    podman__build docker/webapp kolab-webapp --ulimit nofile=65535:65535 \
        ${KOLAB_GIT_REMOTE:+"--build-arg=GIT_REMOTE=$KOLAB_GIT_REMOTE"} \
        ${KOLAB_GIT_REF:+"--build-arg=GIT_REF=$KOLAB_GIT_REF"}
}

podman__build_meet() {
    podman__build docker/webapp kolab-webapp --ulimit nofile=65535:65535 \
        ${KOLAB_GIT_REMOTE:+"--build-arg=GIT_REMOTE=$KOLAB_GIT_REMOTE"} \
        ${KOLAB_GIT_REF:+"--build-arg=GIT_REF=$KOLAB_GIT_REF"}
}

podman__build_roundcube() {
    podman__build docker/roundcube roundcube --ulimit nofile=65535:65535 \
        ${GIT_REMOTE_ROUNDCUBEMAIL:+"--build-arg=GIT_REMOTE_ROUNDCUBEMAIL=$GIT_REMOTE_ROUNDCUBEMAIL"} \
        ${GIT_REF_ROUNDCUBEMAIL:+"--build-arg=GIT_REF_ROUNDCUBEMAIL=$GIT_REF_ROUNDCUBEMAIL"} \
        ${GIT_REMOTE_ROUNDCUBEMAIL_PLUGINS:+"--build-arg=GIT_REMOTE_ROUNDCUBEMAIL_PLUGINS=$GIT_REMOTE_ROUNDCUBEMAIL_PLUGINS"} \
        ${GIT_REF_ROUNDCUBEMAIL_PLUGINS:+"--build-arg=GIT_REF_ROUNDCUBEMAIL_PLUGINS=$GIT_REF_ROUNDCUBEMAIL_PLUGINS"} \
        ${GIT_REMOTE_CHWALA:+"--build-arg=GIT_REMOTE_CHWALA=$GIT_REMOTE_CHWALA"} \
        ${GIT_REF_CHWALA:+"--build-arg=GIT_REF_CHWALA=$GIT_REF_CHWALA"} \
        ${GIT_REMOTE_SYNCROTON:+"--build-arg=GIT_REMOTE_SYNCROTON=$GIT_REMOTE_SYNCROTON"} \
        ${GIT_REF_SYNCROTON:+"--build-arg=GIT_REF_SYNCROTON=$GIT_REF_SYNCROTON"} \
        ${GIT_REMOTE_AUTOCONF:+"--build-arg=GIT_REMOTE_AUTOCONF=$GIT_REMOTE_AUTOCONF"} \
        ${GIT_REF_AUTOCONF:+"--build-arg=GIT_REF_AUTOCONF=$GIT_REF_AUTOCONF"} \
        ${GIT_REMOTE_IRONY:+"--build-arg=GIT_REMOTE_IRONY=$GIT_REMOTE_IRONY"} \
        ${GIT_REF_IRONY:+"--build-arg=GIT_REF_IRONY=$GIT_REF_IRONY"} \
        ${GIT_REMOTE_FREEBUSY:+"--build-arg=GIT_REMOTE_FREEBUSY=$GIT_REMOTE_FREEBUSY"} \
        ${GIT_REF_FREEBUSY:+"--build-arg=GIT_REF_FREEBUSY=$GIT_REF_FREEBUSY"}
}

if [[ $1 == "--podman" ]]; then
    echo "Building with podman"
    shift
    podman__build_base
    podman__build_webapp
    podman__build_meet
    podman build docker/postfix -t kolab-postfix
    podman build docker/imap -t kolab-imap
        ${IMAP_GIT_REMOTE:+"--build-arg=GIT_REMOTE=$IMAP_GIT_REMOTE"} \
        ${IMAP_GIT_REF:+"--build-arg=GIT_REF=$IMAP_GIT_REF"}
    podman build docker/amavis -t kolab-amavis
    podman build docker/collabora -t kolab-collabora --build-arg=REPOSITORY="https://www.collaboraoffice.com/repos/CollaboraOnline/23.05-CODE/CODE-rpm/"
    podman build docker/mariadb -t mariadb
    podman build docker/redis -t redis
    podman build docker/proxy -t kolab-proxy
    podman build docker/coturn -t kolab-coturn
    podman build docker/utils -t kolab-utils
    podman build docker/fluentbit -t fluentbit
    podman__build_roundcube
else
    echo "Building with docker compose"
    # Workaround because docker-compose doesn't know build dependencies, so we build the dependencies first
    # (It does respect depends_on, but we don't actually want the dependencies started, so....)
    docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.build.yml build $@ almalinux9
    docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.build.yml build $@ swoole
    docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.build.yml build $@ webapp
    docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.build.yml build $@
fi

