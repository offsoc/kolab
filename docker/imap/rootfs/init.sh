#!/bin/bash

set -e

sed -i -r \
    -e "s|IMAP_ADMIN_LOGIN|$IMAP_ADMIN_LOGIN|g" \
    -e "s|IMAP_ADMIN_PASSWORD|$IMAP_ADMIN_PASSWORD|g" \
    -e "s|MUPDATE_SERVER|$MUPDATE|g" \
    -e "s|SERVERLIST|$SERVERLIST|g" \
    -e "s|SERVERNAME|$SERVERNAME|g" \
    -e "s|MAXLOGINS_PER_USER|$MAXLOGINS_PER_USER|g" \
    -e "s|TLS_SERVER_CA_FILE|$TLS_SERVER_CA_FILE|g" \
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

if [[ "$WITH_TLS" == "true" ]]; then
    if [[ -f ${SSL_CERTIFICATE} ]]; then
        cat ${SSL_CERTIFICATE} ${SSL_CERTIFICATE_FULLCHAIN} ${SSL_CERTIFICATE_KEY} > /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem
    fi
    sed -i \
        -e "s|# WITH_TLS ||g" \
        /etc/imapd.conf
    sed -i \
        -e "s|# WITH_TLS ||g" \
        /etc/cyrus.conf
fi


if [[ "$WITH_TAGS" == "true" ]]; then
    sed -i \
        -e "s|# WITH_TAGS ||g" \
        /etc/imapd.conf
else
    sed -i \
        -e "s|# WITHOUT_TAGS ||g" \
        /etc/imapd.conf
fi

if [[ "$SYNC_HOST" != "" ]]; then
    sed -i \
        -e "s|# WITH_SYNC_TARGET ||g" \
        -e "s|SYNC_HOST|$SYNC_HOST|g" \
        /etc/imapd.conf
    sed -i \
        -e "s|# WITH_SYNC_TARGET ||g" \
        /etc/cyrus.conf
fi

if [[ "$ROLE" == "frontend" ]]; then
    sed -i \
        -e "s|# WITH_MUPDATE ||g" \
        -e "s|# ROLE_FRONTEND ||g" \
        /etc/imapd.conf
    sed -i \
        -e "s|# ROLE_FRONTEND ||g" \
        /etc/cyrus.conf
    if [[ "$WITH_TLS" == "true" ]]; then
        sed -i \
            -e "s|# ROLE_FRONTEND_WITH_TLS ||g" \
            /etc/cyrus.conf
    fi
elif [[ "$ROLE" == "backend" ]]; then
    sed -i \
        -e "s|# WITH_MUPDATE ||g" \
        -e "s|# ROLE_BACKEND ||g" \
        /etc/imapd.conf
    sed -i \
        -e "s|# WITH_MUPDATE ||g" \
        -e "s|# ROLE_BACKEND ||g" \
        /etc/cyrus.conf
    if [[ "$WITH_TLS" == "true" ]]; then
        sed -i \
            -e "s|# ROLE_BACKEND_WITH_TLS ||g" \
            /etc/cyrus.conf
    fi
else
    sed -i \
        -e "s|# ROLE_BACKEND ||g" \
        /etc/imapd.conf
    sed -i \
        -e "s|# ROLE_BACKEND ||g" \
        /etc/cyrus.conf
    if [[ "$WITH_TLS" == "true" ]]; then
        sed -i \
            -e "s|# ROLE_BACKEND_WITH_TLS ||g" \
            /etc/cyrus.conf
    fi
fi

/usr/sbin/saslauthd -m /run/saslauthd -a httpform -d &
# Can't run as user because of /dev/ permissions so far.
# Cyrus imap only logs to /dev/log, no way around it it seems.
# sudo rsyslogd


# Cyrus needs an entry in /etc/passwd. The alternative would perhaps be the nss_wrapper.
# https://docs.openshift.com/container-platform/3.11/creating_images/guidelines.html#openshift-specific-guidelines
# FIXME: This probably currently just works because we make /etc/ writable, which I suppose we shouldn't.
ID=$(id -u)
GID=$(id -g)
echo "$ID:x:$ID:$GID::/opt/app-root/:/bin/bash" > /etc/passwd

if [[ "$1" == "validate" ]]; then
    # This will print a warning about a missing /var/lib/imap/db/skipstamp, but will still validate the config
    cyr_info conf-lint
    echo "Config validated"
else
    cyr_info conf-lint
    exec env CYRUS_VERBOSE=1 CYRUS_USER="$ID" /usr/libexec/master -D -p /var/run/master.pid
fi


