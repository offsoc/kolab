#!/bin/bash

sed -i -r \
    -e "s|DB_HOST|$DB_HOST|g" \
    -e "s|DB_DATABASE|$DB_DATABASE|g" \
    -e "s|DB_USERNAME|$DB_USERNAME|g" \
    -e "s|DB_PASSWORD|$DB_PASSWORD|g" \
    /etc/pdns/pdns.conf

if [[ $ROLE == "both" ]]; then
    /usr/sbin/pdns_server --guardian=no --daemon=no --disable-syslog --log-timestamp=no --write-pid=no &
    mkdir /run/pdns-recursor
    exec /usr/sbin/pdns_recursor --daemon=no --write-pid=no --log-timestamp=no --disable-syslog
elif [[ $ROLE == "recursor" ]]; then
    exec /usr/sbin/pdns_recursor --daemon=no --write-pid=no --log-timestamp=no --disable-syslog
else
    exec /usr/sbin/pdns_server --guardian=no --daemon=no --disable-syslog --log-timestamp=no --write-pid=no
fi


