#!/bin/bash

if ! grep -q "chatty" /etc/imapd.conf; then
    echo "chatty: 1" >> /etc/imapd.conf
fi

if ! grep -q "debug" /etc/imapd.conf; then
    echo "debug: 1" >> /etc/imapd.conf
fi

systemctl restart cyrus-imapd

sed -i -r -e "s/_debug'] = (.*);/_debug'] = true;/g" /etc/roundcubemail/config.inc.php

if ! grep -q "FLAGS=\"--fork -l debug -d 8\"" /etc/sysconfig/wallace; then
    echo "FLAGS=\"--fork -l debug -d 8\"" > /etc/sysconfig/wallace
fi
systemctl restart wallace
