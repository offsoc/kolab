create_arbitrary_users() {

  # Do not care what option is compulsory here, just create what is specified
  log_info "Creating user specified by (${2}) ..."
mysql $mysql_flags <<EOSQL
    CREATE USER '${2}'@'${4}' IDENTIFIED BY '${3}';
EOSQL

  log_info "Granting privileges to user ${2} for ${1} ..."
mysql $mysql_flags <<EOSQL
      GRANT ALL ON \`${1}\`.* TO '${2}'@'${4}' ;
      FLUSH PRIVILEGES ;
EOSQL
}

DB_NO=1
while [[ ${DB_NO} -ne 0 ]]; do
    DB_CUR="DB_${DB_NO}"
    if [[ -n $(eval echo '${!'${DB_CUR}'*}') ]]; then
        NAME="${DB_CUR}_NAME"
        USER="${DB_CUR}_USER"
        PASS="${DB_CUR}_PASS"
        HOST="${DB_CUR}_HOST"
        create_arbitrary_users ${!NAME} ${!USER} ${!PASS:-Welcome2KolabSystems} ${!HOST:-127.0.0.1} || true
        let "DB_NO+=1"
    else
        DB_NO=0
    fi
done
