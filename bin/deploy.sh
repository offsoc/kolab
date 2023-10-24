#!/bin/bash
bin/quickstart.sh --nodev
if [[ -n $ADMIN_PASSWORD ]]; then
    DOMAIN=$(grep APP_DOMAIN .env | tail -n1 | sed "s/APP_DOMAIN=//")
    docker exec kolab-webapp ./artisan user:password "admin@$DOMAIN" "$ADMIN_PASSWORD"
fi
