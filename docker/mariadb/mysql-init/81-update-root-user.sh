#!/bin/bash

if [ ! -v ${MYSQL_ROOT_PASSWORD} ]; then
  log_info "Update root user for host 127.0.0.1 ..."
mysql $mysql_flags <<EOSQL
  UPDATE mysql.user SET Password = PASSWORD('${MYSQL_ROOT_PASSWORD}') WHERE User = 'root' AND Host = '127.0.0.1';
  FLUSH PRIVILEGES; 
EOSQL
fi

