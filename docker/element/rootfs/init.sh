#!/bin/bash

set -e

sed -i -r \
    -e "s|APP_DOMAIN|$APP_DOMAIN|g" \
    /etc/nginx/nginx.conf

if [[ ! -f /opt/element-web/config.json ]]; then
    cp /opt/app-root/src/config.json /opt/element-web/config.json
    sed -i -r \
        -e "s|APP_DOMAIN|$APP_DOMAIN|g" \
        /opt/element-web/config.json
fi

if [[ $1 == "validate" ]]; then
    exec nginx -t
else
    exec nginx -g "daemon off;"
fi
