create_arbitrary_users() {
    mysql $mysql_flags << EOF
ALTER USER $DB_HKCCP_USERNAME@'%' IDENTIFIED BY '$DB_HKCCP_PASSWORD';
FLUSH PRIVILEGES;
EOF

    mysql $mysql_flags << EOF
ALTER USER $DB_KOLAB_USERNAME@'%' IDENTIFIED BY '$DB_KOLAB_PASSWORD';
FLUSH PRIVILEGES;
EOF

    mysql $mysql_flags << EOF
ALTER USER $DB_RC_USERNAME@'%' IDENTIFIED BY '$DB_RC_PASSWORD';
FLUSH PRIVILEGES;
EOF

}

if ! [ -v MYSQL_RUNNING_AS_SLAVE ]; then
    create_arbitrary_users
fi
