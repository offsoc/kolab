#!/bin/bash

set -e
set -x

APP_DOMAIN=$(grep APP_DOMAIN .env | tail -n1 | sed "s/APP_DOMAIN=//")
ADMIN_PASSWORD="simple123"

docker compose exec postfix testsaslauthd -u "admin@$APP_DOMAIN" -p "$ADMIN_PASSWORD"
docker compose exec imap testsaslauthd -u "admin@$APP_DOMAIN" -p "$ADMIN_PASSWORD"

utils/mailtransporttest.py --sender-username "admin@$APP_DOMAIN" --sender-password "$ADMIN_PASSWORD" --sender-host "$APP_DOMAIN" --recipient-username "admin@$APP_DOMAIN" --recipient-password "$ADMIN_PASSWORD" --recipient-host "$APP_DOMAIN"

utils/kolabendpointtester.py --verbose --host "$APP_DOMAIN" --dav "https://$APP_DOMAIN/dav/" --imap "$APP_DOMAIN" --activesync "$APP_DOMAIN"  --user "admin@$APP_DOMAIN" --password "$ADMIN_PASSWORD"

echo "All tests have passed!"
