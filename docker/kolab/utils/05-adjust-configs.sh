#!/bin/bash

. ./settings.sh

cat ${SSL_CERTIFICATE} ${SSL_CERTIFICATE_FULLCHAIN} ${SSL_CERTIFICATE_KEY} > /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem
chown cyrus:mail /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem

cp /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem /etc/pki/tls/private/postfix.pem
chown postfix:mail /etc/pki/tls/private/postfix.pem
chmod 655 /etc/pki/tls/private/postfix.pem
