#!/bin/bash

mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD} \
    -e "CREATE DATABASE IF NOT EXISTS ${DB_HKCCP_DATABASE};"

mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD} \
    -e "GRANT ALL PRIVILEGES ON ${DB_HKCCP_DATABASE}.* TO '${DB_HKCCP_USERNAME}'@'%' IDENTIFIED BY '${DB_HKCCP_PASSWORD}';"

mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD} \
    -e "FLUSH PRIVILEGES;"

