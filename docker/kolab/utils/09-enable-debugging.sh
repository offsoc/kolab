#!/bin/bash

echo "chatty: 1" >> /etc/imapd.conf
echo "debug: 1" >> /etc/imapd.conf

systemctl restart cyrus-imapd

sed -i -r -e "s/_debug'] = (.*);/_debug'] = true;/g" /etc/roundcubemail/config.inc.php

echo "FLAGS=\"--fork -l debug -d 8\"" > /etc/sysconfig/wallace
systemctl restart wallace
