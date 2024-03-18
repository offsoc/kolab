#!/bin/bash

set -e

cat ${SSL_CERTIFICATE} ${SSL_CERTIFICATE_FULLCHAIN} ${SSL_CERTIFICATE_KEY} > /etc/pki/tls/private/postfix.pem
chown postfix:mail /etc/pki/tls/private/postfix.pem
chmod 655 /etc/pki/tls/private/postfix.pem

chown -R postfix:mail /var/lib/postfix
chown -R postfix:mail /var/spool/postfix
/usr/sbin/postfix set-permissions

sed -i -r \
    -e "s|APP_SERVICES_DOMAIN|$APP_SERVICES_DOMAIN|g" \
    -e "s|SERVICES_PORT|$SERVICES_PORT|g" \
    /etc/saslauthd.conf

/usr/sbin/saslauthd -m /run/saslauthd -a httpform -d &

# If host mounting /var/spool/postfix, we need to delete old pid file before
# starting services
rm -f /var/spool/postfix/pid/master.pid

/usr/libexec/postfix/aliasesdb
/usr/libexec/postfix/chroot-update

sed -i -r \
    -e "s|LMTP_DESTINATION|$LMTP_DESTINATION|g" \
    -e "s|APP_DOMAIN|$APP_DOMAIN|g" \
    -e "s|MYNETWORKS|$MYNETWORKS|g" \
    -e "s|AMAVIS_HOST|$AMAVIS_HOST|g" \
    /etc/postfix/main.cf

sed -i -r \
    -e "s|MYNETWORKS|$MYNETWORKS|g" \
    -e "s|AMAVIS_HOST|$AMAVIS_HOST|g" \
    /etc/postfix/master.cf

sed -i -r \
    -e "s|SERVICES_HOST|http://$APP_SERVICES_DOMAIN:$SERVICES_PORT|g" \
    /usr/libexec/postfix/kolab_policy*

sed -i -r \
    -e "s|DB_HOST|$DB_HOST|g" \
    -e "s|DB_USERNAME|$DB_USERNAME|g" \
    -e "s|DB_PASSWORD|$DB_PASSWORD|g" \
    -e "s|DB_DATABASE|$DB_DATABASE|g" \
    /etc/postfix/sql/*

# echo "/$APP_DOMAIN/              lmtp:$LMTP_DESTINATION" >> /etc/postfix/transport
# postmap /etc/postfix/transport

/usr/sbin/postfix check
exec /usr/sbin/postfix -c /etc/postfix start-fg
