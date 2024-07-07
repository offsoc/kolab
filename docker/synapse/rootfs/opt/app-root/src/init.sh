#!/bin/bash
set -e

if [[ -f /etc/certs/ca.cert ]]; then
    cp /etc/certs/ca.cert /etc/pki/ca-trust/source/anchors/
    update-ca-trust
fi

sed -i -r \
    -e "s|APP_DOMAIN|$APP_DOMAIN|g" \
    -e "s|KOLAB_URL|$KOLAB_URL|g" \
    -e "s|TURN_SHARED_SECRET|$TURN_SHARED_SECRET|g" \
    -e "s|TURN_URIS|$TURN_URIS|g" \
    -e "s|SYNAPSE_OAUTH_CLIENT_ID|$SYNAPSE_OAUTH_CLIENT_ID|g" \
    -e "s|SYNAPSE_OAUTH_CLIENT_SECRET|$SYNAPSE_OAUTH_CLIENT_SECRET|g" \
    /opt/app-root/src/homeserver.yaml

exec synctl --no-daemonize start ${CONFIGFILE:-/opt/app-root/src/homeserver.yaml}
