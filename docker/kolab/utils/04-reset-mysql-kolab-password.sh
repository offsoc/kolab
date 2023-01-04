#!/bin/bash

mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD} \
    -e "SET PASSWORD FOR '${DB_HKCCP_USERNAME}'@'%' = PASSWORD('${DB_HKCCP_PASSWORD}');"

mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD} \
    -e "SET PASSWORD FOR '${DB_KOLAB_USERNAME}'@'localhost' = PASSWORD('${DB_KOLAB_PASSWORD}');"

mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD} \
    -e "CREATE USER '${DB_KOLAB_USERNAME}'@'%' IDENTIFIED BY '${DB_KOLAB_PASSWORD}'; FLUSH PRIVILEGES;"
