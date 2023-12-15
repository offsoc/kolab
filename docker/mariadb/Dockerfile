FROM mariadb:10.9

COPY mysql-init/setup.sh /docker-entrypoint-initdb.d/
COPY mysql-update/update.sh /always-initdb.d/
