#!/bin/bash

sqlpw=$(grep ^sql_uri /etc/kolab/kolab.conf | awk -F':' '{print $3}' | awk -F'@' '{print $1}')

mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
    -e "SET PASSWORD FOR '${DB_HKCCP_USERNAME:-kolabdev}'@'%' = PASSWORD('${DB_HKCCP_PASSWORD:-Welcome2KolabSystems}');"

mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
    -e "SET PASSWORD FOR '${DB_KOLAB_USERNAME:-kolab}'@'%' = PASSWORD('${DB_KOLAB_PASSWORD:=$sqlpw}');"

mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
    -e "SET PASSWORD FOR '${DB_RC_USERNAME:-roundcube}'@'%' = PASSWORD('${DB_RC_PASSWORD:-Welcome2KolabSystems}');"
