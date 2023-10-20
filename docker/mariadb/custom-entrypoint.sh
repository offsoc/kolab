#!/usr/bin/env bash
set -Eeo pipefail
set -x

# Example using the functions of the MariaDB entrypoint to customize startup to always run files in /always-initdb.d/

rm -rf /var/lib/mysql/*
ls /var/lib/mysql

source "$(which docker-entrypoint.sh)"

docker_setup_env "$@"
ls /var/lib/mysql
echo "Already exists $DATABASE_ALREADY_EXISTS"
docker_create_db_directories

if [ -z "$DATABASE_ALREADY_EXISTS" ]; then
        mysql_note "DB does not already exist"
        docker_verify_minimum_env
        docker_init_database_dir "$@"

        mysql_note "Starting temporary server"
        docker_temp_server_start "$@"
        mysql_note "Temporary server started."

        docker_setup_db
        docker_process_init_files /docker-entrypoint-initdb.d/*

        mysql_note "Stopping temporary server"
        docker_temp_server_stop
        mysql_note "Temporary server stopped"

        echo
        mysql_note "MySQL init process done. Ready for start up."
        echo
else
        docker_temp_server_start $@
        docker_process_init_files /always-initdb.d/*
        docker_temp_server_stop
fi

# exec mysqld
su -c mysqld  mysql
