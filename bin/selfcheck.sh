#!/bin/bash

set -e
set -x

APP_DOMAIN=$(grep APP_DOMAIN .env | tail -n1 | sed "s/APP_DOMAIN=//")
if [ -z "$ADMIN_PASSWORD" ]; then
    ADMIN_PASSWORD="simple123"
fi
if [ -z "$ADMIN_USER" ]; then
    ADMIN_USER="admin@$APP_DOMAIN"
fi

docker compose exec postfix testsaslauthd -u "$ADMIN_USER" -p "$ADMIN_PASSWORD"
docker compose exec imap testsaslauthd -u "$ADMIN_USER" -p "$ADMIN_PASSWORD"

utils/mailtransporttest.py --sender-username "$ADMIN_USER" --sender-password "$ADMIN_PASSWORD" --sender-host "$APP_DOMAIN" --recipient-username "$ADMIN_USER" --recipient-password "$ADMIN_PASSWORD" --recipient-host "$APP_DOMAIN"

utils/kolabendpointtester.py --verbose --host "$APP_DOMAIN" --dav "https://$APP_DOMAIN/dav/" --imap "$APP_DOMAIN" --activesync "$APP_DOMAIN"  --user "$ADMIN_USER" --password "$ADMIN_PASSWORD"

echo "All tests have passed!"
