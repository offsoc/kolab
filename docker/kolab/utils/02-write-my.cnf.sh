#!/bin/bash

cat > /root/.my.cnf << EOF
[client]
host=${DB_HOST}
user=root
password=${DB_ROOT_PASSWORD}
EOF
