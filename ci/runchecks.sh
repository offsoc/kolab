#!/bin/bash

set -x
set -e

# Setup
git stash
git pull
docker compose pull --ignore-buildable
env HOST=kolab.local ADMIN_PASSWORD=simple123 bin/configure.sh config.demo
bin/quickstart.sh --nodev
pip install virtualenv
virtualenv venv
source venv/bin/activate
pip install dnspython

# Ensure the environment is functional
env ADMIN_USER=john@kolab.org ADMIN_PASSWORD=simple123 bin/selfcheck.sh

# Run the tests
docker rm kolab-tests >/dev/null 2>/dev/null || :
docker run --rm --network=kolab_kolab -v ${PWD}/src:/src/kolabsrc.orig --name kolab-tests -t kolab-tests /init.sh testsuite
