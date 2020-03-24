#!/bin/bash

echo "chatty: 1" >> /etc/imapd.conf
echo "debug: 1" >> /etc/imapd.conf

sed -i -r \
    -e '/allowplaintext/ a\
imaplain_allowplaintext: yes' \
    /etc/imapd.conf

cp /etc/cyrus.conf /etc/cyrus.conf.orig

sed -i \
    -e '/SERVICES/ a\
    imaplain cmd="imapd" listen=127.0.0.1:10143 prefork=1' \
    -e '/SERVICES/ a\
    imap cmd="imapd" listen=127.0.0.1:9143 prefork=1' \
    /etc/cyrus.conf

systemctl restart cyrus-imapd

sed -i -r -e "s/_debug'] = (.*);/_debug'] = true;/g" /etc/roundcubemail/config.inc.php
