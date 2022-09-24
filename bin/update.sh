#!/bin/bash
docker-compose down --remove-orphans
docker-compose build coturn kolab mariadb meet pdns proxy redis haproxy webapp
bin/regen-certs
docker-compose up -d coturn kolab mariadb meet pdns proxy redis haproxy webapp
