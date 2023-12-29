#!/bin/bash

# Workaround because docker-compose doesn't know build dependencies, so we build the dependencies first
# (It does respect depends_on, but we don't actually want the dependencies started, so....)
docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.build.yml build swoole
docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.build.yml build webapp
docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.build.yml build
