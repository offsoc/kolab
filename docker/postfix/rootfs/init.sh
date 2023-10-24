#!/bin/bash

cat ${SSL_CERTIFICATE} ${SSL_CERTIFICATE_FULLCHAIN} ${SSL_CERTIFICATE_KEY} > /etc/pki/tls/private/postfix.pem
chown postfix:mail /etc/pki/tls/private/postfix.pem
chmod 655 /etc/pki/tls/private/postfix.pem

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
    -e "s|MYNETWORKS|172.18.0.0/24|g" \
    /etc/postfix/main.cf

sed -i -r \
    -e "s|SERVICES_HOST|http://services.$APP_DOMAIN:8000|g" \
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
