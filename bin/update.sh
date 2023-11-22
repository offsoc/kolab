#!/bin/bash
docker compose down --remove-orphans
docker compose pull --ignore-buildable
bin/reconfigure.sh
docker compose build
bin/regen-certs
docker compose up -d --wait
