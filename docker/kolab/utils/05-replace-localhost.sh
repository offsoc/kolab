#!/bin/bash

if [[ ${DB_HOST} == "localhost" || ${DB_HOST} == "127.0.0.1" ]]; then
    mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
        -e "UPDATE mysql.db SET Host = '127.0.0.1' WHERE Host = 'localhost';"
    
    mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
        -e "UPDATE mysql.user SET Host = '127.0.0.1' WHERE Host = 'localhost';"
    
    mysql -h ${DB_HOST:-127.0.0.1} -u root --password=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
        -e "FLUSH PRIVILEGES;"
fi

sed -i -e "s#^ldap_servers:.*#ldap_servers: ldap://${LDAP_HOST:-127.0.0.1}:389#" /etc/imapd.conf
sed -i -e "/hosts/s/localhost/${LDAP_HOST:-127.0.0.1}/" /etc/iRony/dav.inc.php
sed -i -e "s#^ldap_uri.*#ldap_uri = ldap://${LDAP_HOST:-127.0.0.1}:389#" \
       -e "s#^cache_uri.*mysql://\(.*\):\(.*\)@\(.*\)\/\(.*\)#cache_uri = mysql://${DB_KOLAB_USERNAME:-\1}:${DB_KOLAB_PASSWORD:-\2}@${DB_HOST:-127.0.0.1}/${DB_KOLAB_DATABASE:-\4}#" \
       -e "s#^sql_uri.*mysql://\(.*\):\(.*\)@\(.*\)\/\(.*\)#sql_uri = mysql://${DB_KOLAB_USERNAME:-\1}:${DB_KOLAB_PASSWORD:-\2}@${DB_HOST:-127.0.0.1}/${DB_KOLAB_DATABASE:-\4}#" \
       -e "s#^uri.*#uri = imaps://${IMAP_HOST:-127.0.0.1}:993#" /etc/kolab/kolab.conf
sed -i -e "/host/s/localhost/${LDAP_HOST:-127.0.0.1}/g" \
       -e "/fbsource/s/localhost/${IMAP_HOST:-127.0.0.1}/g" /etc/kolab-freebusy/config.ini
sed -i -e "s/server_host.*/server_host = ${LDAP_HOST:-127.0.0.1}/g" /etc/postfix/ldap/*
sed -i -e "/password_ldap_host/s/localhost/${LDAP_HOST:-127.0.0.1}/" /etc/roundcubemail/password.inc.php
sed -i -e "/hosts/s/localhost/${LDAP_HOST:-127.0.0.1}/" /etc/roundcubemail/kolab_auth.inc.php
sed -i -e "#db_dsnw#s#=.*$#= mysqli//${DB_RC_USERNAME:-roundcube}:${DB_RC_PASSWORD:-Welcome2KolabSystems}@${DB_HOST:-127.0.0.1}/${DB_RC_DATABASE:-roundcube}#" \
       -e "/default_host/s/localhost/${IMAP_HOST:-127.0.0.1}/" \
       -e "/smtp_server/s/localhost/${MAIL_HOST:-127.0.0.1}/" \
       -e "/hosts/s/localhost/${LDAP_HOST:-127.0.0.1}/" /etc/roundcubemail/config.inc.php
sed -i -e "/hosts/s/localhost/${LDAP_HOST:-127.0.0.1}/" /etc/roundcubemail/calendar.inc.php

systemctl restart cyrus-imapd postfix
