#!/bin/bash

MYSQL_PWD=$MARIADB_ROOT_PASSWORD mysql --protocol=socket -uroot -hlocalhost --socket="/run/mysqld/mysqld.sock" << EOF
ALTER USER $DB_HKCCP_USERNAME@'%' IDENTIFIED BY '$DB_HKCCP_PASSWORD';
FLUSH PRIVILEGES;
EOF

MYSQL_PWD=$MARIADB_ROOT_PASSWORD mysql --protocol=socket -uroot -hlocalhost --socket="/run/mysqld/mysqld.sock" << EOF
ALTER USER $DB_KOLAB_USERNAME@'%' IDENTIFIED BY '$DB_KOLAB_PASSWORD';
FLUSH PRIVILEGES;
EOF
