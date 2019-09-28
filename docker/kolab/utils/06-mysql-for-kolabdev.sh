#!/bin/bash

mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
    -e "CREATE DATABASE kolabdev;"

mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
    -e "GRANT ALL PRIVILEGES ON kolabdev.* TO 'kolabdev'@'127.0.0.1' IDENTIFIED BY 'kolab';"

mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
    -e "FLUSH PRIVILEGES;"

