#!/bin/bash

cat > /root/.my.cnf << EOF
[client]
host=${DB_HOST:-127.0.0.1}
user=root
password=${DB_ROOT_PASSWORD:-Welcome2KolabSystems}
EOF
