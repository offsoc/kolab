#!/bin/bash

if [[ $1 == "--podman" ]]; then
    podman build docker/swoole/ -t apheleia/swoole
    podman build docker/base/ -f almalinux8 -t apheleia/almalinux8
    podman build docker/base/ -f almalinux9 -t apheleia/almalinux9
    podman build --ulimit nofile=65535:65535 docker/webapp -t kolab-webapp \
        --build-arg GIT_REMOTE=${KOLAB_GIT_REMOTE} --build-arg GIT_REF=${KOLAB_GIT_REF} 
    podman build --ulimit nofile=65535:65535 docker/meet -t kolab-meet \
        --build-arg GIT_REMOTE=${KOLAB_GIT_REMOTE} --build-arg GIT_REF=${KOLAB_GIT_REF} 
    podman build docker/postfix -t kolab-postfix
    podman build docker/imap -t kolab-imap
    podman build docker/amavis -t kolab-amavis
    podman build docker/collabora -t kolab-collabora --build-arg REPOSITORY="https://www.collaboraoffice.com/repos/CollaboraOnline/23.05-CODE/CODE-rpm/"
    podman build docker/mariadb -t mariadb
    podman build docker/redis -t redis
    podman build docker/proxy -t kolab-proxy
    podman build docker/coturn -t kolab-coturn
    podman build docker/utils -t kolab-utils
    podman build docker/fluentbit -t fluentbit
    podman build --ulimit nofile=65535:65535 docker/roundcube -t roundcube \
        --build-arg GIT_REMOTE=${GIT_REMOTE_ROUNDCUBEMAIL} --build-arg GIT_REF=${GIT_REF_ROUNDCUBEMAIL} \
        --build-arg GIT_REMOTE=${GIT_REMOTE_ROUNDCUBEMAIL_PLUGINS} --build-arg GIT_REF=${GIT_REF_ROUNDCUBEMAIL_PLUGINS} \
        --build-arg GIT_REMOTE=${GIT_REMOTE_CHWALA} --build-arg GIT_REF=${GIT_REF_CHWALA} \
        --build-arg GIT_REMOTE=${GIT_REMOTE_SYNCROTON} --build-arg GIT_REF=${GIT_REF_SYNCROTON} \
        --build-arg GIT_REMOTE=${GIT_REMOTE_AUTOCONF} --build-arg GIT_REF=${GIT_REF_AUTOCONF} \
        --build-arg GIT_REMOTE=${GIT_REMOTE_IRONY} --build-arg GIT_REF=${GIT_REF_IRONY} \
        --build-arg GIT_REMOTE=${GIT_REMOTE_FREEBUSY} --build-arg GIT_REF=${GIT_REF_FREEBUSY} \
else
    # Workaround because docker-compose doesn't know build dependencies, so we build the dependencies first
    # (It does respect depends_on, but we don't actually want the dependencies started, so....)
    docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.build.yml build $@ swoole almalinux8 almalinux9
    docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.build.yml build $@ webapp
    docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.build.yml build $@
fi

