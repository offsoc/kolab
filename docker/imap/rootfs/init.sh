#!/bin/bash


sed -i -r \
    -e "s|IMAP_ADMIN_LOGIN|$IMAP_ADMIN_LOGIN|g" \
    -e "s|IMAP_ADMIN_PASSWORD|$IMAP_ADMIN_PASSWORD|g" \
    $IMAPD_CONF

sed -i -r \
    -e "s|APP_SERVICES_DOMAIN|$APP_SERVICES_DOMAIN|g" \
    -e "s|SERVICES_PORT|$SERVICES_PORT|g" \
    /etc/saslauthd.conf

if [[ "$CYRUS_CONF" != "/etc/cyrus.conf" ]]; then
    cp "$CYRUS_CONF" /etc/cyrus.conf
fi

if [[ "$IMAPD_CONF" != "/etc/imapd.conf" ]]; then
    cp "$IMAPD_CONF" /etc/imapd.conf
fi

mkdir -p /var/lib/imap/socket
mkdir -p /var/lib/imap/db

if [[ -f ${SSL_CERTIFICATE} ]]; then
    cat ${SSL_CERTIFICATE} ${SSL_CERTIFICATE_FULLCHAIN} ${SSL_CERTIFICATE_KEY} > /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem
    chown 1001:0 /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem
fi

/usr/sbin/saslauthd -m /run/saslauthd -a httpform -d &
# Can't run as user because of /dev/ permissions so far.
# Cyrus imap only logs to /dev/log, no way around it it seems.
# sudo rsyslogd


# Cyrus needs an entry in /etc/passwd. THe alternative would be perhaps the nss_wrapper
# https://docs.openshift.com/container-platform/3.11/creating_images/guidelines.html#openshift-specific-guidelines
# FIXME: This probably currently just works because we make /etc/ writable, which I suppose we shouldn't.
ID=$(id -u)
GID=$(id -g)
echo "$ID:x:$ID:$GID::/opt/app-root/:/bin/bash" > /etc/passwd

exec env CYRUS_VERBOSE=1 CYRUS_USER="$ID" /usr/libexec/master -D -p /var/run/master.pid


