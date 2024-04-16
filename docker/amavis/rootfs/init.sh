#!/bin/bash

set -e

CONFIG="/etc/amavisd/amavisd.conf"
sed -i -r \
    -e "s|APP_DOMAIN|$APP_DOMAIN|g" \
    -e "s|POSTFIX_HOST|$POSTFIX_HOST|g" \
    $CONFIG

DKIMKEYFILE="/var/dkim/$APP_DOMAIN.$DKIM_IDENTIFIER.pem"
if ! [ -f $DKIMKEYFILE ]; then
    echo "Generating the DKIM keys at: $DKIMKEYFILE"
    amavisd -c $CONFIG genrsa $DKIMKEYFILE 2048
    chmod g+r $DKIMKEYFILE
    chgrp amavis $DKIMKEYFILE
    chown -R amavis:amavis /var/dkim
fi

sed -i -r \
    -e "s|DKIM_IDENTIFIER|$DKIM_IDENTIFIER|g" \
    $CONFIG

# We use these to check if the process has started, so ensure we aren't dealing wiht leftover files
rm -f /var/run/amavisd/amavisd.pid
rm -f /var/run/amavisd/clamd.pid

mkdir -p /var/run/amavisd
chmod 777 /var/run/amavisd
mkdir -p /var/spool/amavisd/tmp
mkdir -p /var/spool/amavisd/db
mkdir -p /var/spool/amavisd/quarantine
chown -R amavis:amavis /var/spool/amavisd
chown -R clamupdate:clamupdate /var/lib/clamav

echo "DKIM keys:"
amavisd -c $CONFIG showkeys

# Initialize the clamav db.
if $CLAMD; then
    echo "Updating clamav db"
    # If we run this too frequently we'll be rate-limited via HTTP 429
    /usr/bin/freshclam --datadir=/var/lib/clamav
    # Update once per day via daemon
    /usr/bin/freshclam -d -c 1
fi

# Update the spam db every 30h
echo "Updating spamassassin db"
sa-update -v || :
##FIXME this probably doesn't work since we exec to amavisd
#(
#while true; do
#	sleep 30h
#	sa-update -v
#done
#) &

if $CLAMD; then
    echo "Starting clamd"
    clamd --config-file=/etc/clamd.d/amavisd.conf
fi

# This allows to kill amavis to reload the config or code in a running container
if $DEBUG; then
    echo "Starting amavis in debug mode"
    while true; do
        amavisd -c $CONFIG foreground
    done
fi
echo "Starting amavis"
exec amavisd -c $CONFIG foreground
