#!/bin/bash

set -x
set -e

# Setup
env HOST=kolab.local ADMIN_PASSWORD=simple123 bin/configure.sh config.demo
# docker compose pull --ignore-buildable
bin/quickstart.sh --nodev

# Ensure the environment is functional
# env ADMIN_USER=john@kolab.org ADMIN_PASSWORD=simple123 bin/selfcheck.sh
ADMIN_USER=john@kolab.org
ADMIN_PASSWORD=simple123
APP_DOMAIN=$(grep APP_DOMAIN .env | tail -n1 | sed "s/APP_DOMAIN=//")
docker compose exec postfix testsaslauthd -u "$ADMIN_USER" -p "$ADMIN_PASSWORD"
docker compose exec imap testsaslauthd -u "$ADMIN_USER" -p "$ADMIN_PASSWORD"
docker compose -f docker-compose.yml -f docker-compose.build.yml run -ti --rm  utils ./kolabendpointtester.py --verbose --host "$APP_DOMAIN" --dav "https://$APP_DOMAIN/dav/" --imap "$APP_DOMAIN" --activesync "$APP_DOMAIN"  --user "$ADMIN_USER" --password "$ADMIN_PASSWORD"

# Run the tests
docker rm kolab-tests >/dev/null 2>/dev/null || :
docker run --rm --network=kolab_kolab -v ${PWD}/src:/src/kolabsrc.orig --name kolab-tests -t kolab-tests /init.sh testsuite
