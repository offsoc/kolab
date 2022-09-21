#!/bin/bash

# if [[ ${DB_HOST} == "localhost" || ${DB_HOST} == "127.0.0.1" ]]; then
#     mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
#         -e "UPDATE mysql.db SET Host = '127.0.0.1' WHERE Host = 'localhost';"
    
#     mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
#         -e "FLUSH PRIVILEGES;"
# fi

sed -i -e "/hosts/s/localhost/${LDAP_HOST:-127.0.0.1}/" /etc/iRony/dav.inc.php
sed -i -e "/host/s/localhost/${LDAP_HOST:-127.0.0.1}/g" \
       -e "/fbsource/s/localhost/${IMAP_HOST:-127.0.0.1}/g" /etc/kolab-freebusy/config.ini
#sed -i -e "s/server_host.*/server_host = ${LDAP_HOST:-127.0.0.1}/g" /etc/postfix/ldap/*
sed -i -e "/password_ldap_host/s/localhost/${LDAP_HOST:-127.0.0.1}/" /etc/roundcubemail/password.inc.php
sed -i -e "/hosts/s/localhost/${LDAP_HOST:-127.0.0.1}/" /etc/roundcubemail/kolab_auth.inc.php
sed -i -e "s#.*db_dsnw.*#    \$config['db_dsnw'] = 'mysql://${DB_RC_USERNAME}:${DB_RC_PASSWORD}@${DB_HOST}/roundcube';#" \
       -e "/default_host/s|= .*$|= 'ssl://${IMAP_HOST:-127.0.0.1}';|" \
       -e "/default_port/s|= .*$|= ${IMAP_PORT:-11993};|" \
       -e "/smtp_server/s|= .*$|= 'tls://${MAIL_HOST:-127.0.0.1}';|" \
       -e "/smtp_port/s/= .*$/= ${MAIL_PORT:-10587};/" \
       -e "/hosts/s/localhost/${LDAP_HOST:-127.0.0.1}/" /etc/roundcubemail/config.inc.php
sed -i -e "/hosts/s/localhost/${LDAP_HOST:-127.0.0.1}/" /etc/roundcubemail/calendar.inc.php

systemctl restart postfix
