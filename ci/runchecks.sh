#!/bin/bash

set -x
set -e

git pull
docker compose pull --ignore-buildable
env HOST=kolab.local ADMIN_PASSWORD=simple123 bin/configure.sh config.prod
env ADMIN_PASSWORD=simple123 bin/deploy.sh
pip install virtualenv
virtualenv venv
source venv/bin/activate
pip install dnspython
env ADMIN_PASSWORD=simple123 bin/selfcheck.sh
