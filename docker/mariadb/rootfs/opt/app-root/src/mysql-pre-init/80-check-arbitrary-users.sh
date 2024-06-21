check_arbitrary_users() {
  if ! [[ -v DB_HKCCP_USERNAME && -v DB_HKCCP_PASSWORD && -v DB_HKCCP_DATABASE ]]; then
    echo "You need to specify all these variables: DB_HKCCP_USERNAME, DB_HKCCP_PASSWORD, and DB_HKCCP_DATABASE"
    return 1
  fi
  if ! [[ -v DB_KOLAB_USERNAME && -v DB_KOLAB_PASSWORD && -v DB_KOLAB_DATABASE ]]; then
    echo "You need to specify all these variables: DB_KOLAB_USERNAME, DB_KOLAB_PASSWORD, and DB_KOLAB_DATABASE"
    return 1
  fi
  if ! [[ -v DB_RC_USERNAME && -v DB_RC_PASSWORD && -v DB_RC_DATABASE ]]; then
    echo "You need to specify all these variables: DB_RC_USERNAME, DB_RC_PASSWORD, and DB_RC_DATABASE"
    return 1
  fi
}

if ! [ -v MYSQL_RUNNING_AS_SLAVE ]; then
  check_arbitrary_users
fi
