#!/bin/bash

sqlpw=$(grep ^sql_uri /etc/kolab/kolab.conf | awk -F':' '{print $3}' | awk -F'@' '{print $1}')

mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
    -e "SET PASSWORD FOR 'kolab'@'localhost' = PASSWORD('${sqlpw}');"

