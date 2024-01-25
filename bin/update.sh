#!/bin/bash
docker compose down --remove-orphans

docker pull quay.io/sclorg/mariadb-105-c9s
docker pull minio/minio:latest
docker pull almalinux:8
docker pull almalinux:9
docker pull fedora:35
docker pull fedora:37

bin/reconfigure.sh
docker compose build
bin/regen-certs
docker compose up -d --wait
