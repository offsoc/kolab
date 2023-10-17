#!/bin/bash


sed -i -r \
    -e "s|IMAP_ADMIN_LOGIN|$IMAP_ADMIN_LOGIN|g" \
    -e "s|IMAP_ADMIN_PASSWORD|$IMAP_ADMIN_PASSWORD|g" \
    /etc/imapd.conf

sed -i -r \
    -e "s|APP_DOMAIN|$APP_DOMAIN|g" \
    /etc/saslauthd.conf


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

exec env CYRUS_VERBOSE=2 CYRUS_USER="$ID" /usr/libexec/master -D -p /var/run/master.pid -M /etc/cyrus.conf -C /etc/imapd.conf


