FROM mariadb:10.9

COPY mysql-init/setup.sh /docker-entrypoint-initdb.d/
COPY mysql-init/roundcube.mysql.initial.sql /home/
COPY mysql-update/update.sh /always-initdb.d/
