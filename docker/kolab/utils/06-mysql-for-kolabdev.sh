#!/bin/bash

mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
    -e "CREATE DATABASE IF NOT EXISTS ${DB_HKCCP_DATABASE:-kolabdev};"

mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
    -e "GRANT ALL PRIVILEGES ON ${DB_HKCCP_DATABASE:-kolabdev}.* TO '${DB_HKCCP_USERNAME:-kolabdev}'@'%' IDENTIFIED BY '${DB_HKCCP_PASSWORD:-kolab}';"

mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
    -e "FLUSH PRIVILEGES;"

