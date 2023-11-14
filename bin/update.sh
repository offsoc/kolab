#!/bin/bash
docker compose down --remove-orphans
docker compose pull --ignore-buildable
docker compose build
bin/regen-certs
docker compose up -d --wait
