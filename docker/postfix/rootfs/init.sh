#!/bin/bash

sed -i -r \
    -e "s|APP_DOMAIN|$APP_DOMAIN|g" \
    /etc/saslauthd.conf

/usr/sbin/saslauthd -m /run/saslauthd -a httpform -d &

# If host mounting /var/spool/postfix, we need to delete old pid file before
# starting services
rm -f /var/spool/postfix/pid/master.pid

/usr/libexec/postfix/aliasesdb
/usr/libexec/postfix/chroot-update

sed -i -r \
    -e "s|LMTP_DESTINATION|$LMTP_DESTINATION|g" \
    /etc/postfix/main.cf

sed -i -r \
    -e "s|APP_DOMAIN|$APP_DOMAIN|g" \
    /etc/postfix/main.cf

sed -i -r \
    -e "s|APP_DOMAIN|$APP_DOMAIN|g" \
    /usr/libexec/postfix/kolab_policy*

sed -i -r \
    -e "s|DB_HOST|$DB_HOST|g" \
    -e "s|DB_USERNAME|$DB_USERNAME|g" \
    -e "s|DB_PASSWORD|$DB_PASSWORD|g" \
    -e "s|DB_DATABASE|$DB_DATABASE|g" \
    /etc/postfix/sql/*

# echo "/$APP_DOMAIN/              lmtp:$LMTP_DESTINATION" >> /etc/postfix/transport
# postmap /etc/postfix/transport

exec /usr/sbin/postfix -c /etc/postfix start-fg
