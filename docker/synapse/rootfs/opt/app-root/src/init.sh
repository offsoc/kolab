#!/bin/bash
set -e

sed -i -r \
    -e "s|APP_DOMAIN|$APP_DOMAIN|g" \
    -e "s|KOLAB_URL|$KOLAB_URL|g" \
    -e "s|TURN_SHARED_SECRET|$TURN_SHARED_SECRET|g" \
    -e "s|TURN_URIS|$TURN_URIS|g" \
    /opt/app-root/src/homeserver.yaml

exec synctl --no-daemonize start ${CONFIGFILE:-/opt/app-root/src/homeserver.yaml}
