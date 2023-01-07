#!/bin/bash

. ./settings.sh


echo ${CMD} | tee -a /root/setup-kolab.log
echo -n "Wait for MariaDB container: " | tee -a /root/setup-kolab.log
while ! mysqladmin -u root ping > /dev/null 2>&1 ; do
        echo -n '.'
        sleep 3
done | tee -a /root/setup-kolab.log
echo "OK!" | tee -a /root/setup-kolab.log

echo -n "Wait for DS389 container: " | tee -a /root/setup-kolab.log
while ! ldapsearch -h ${LDAP_HOST} -D "${LDAP_ADMIN_BIND_DN}" -w "${LDAP_ADMIN_BIND_PW}" -b "" -s base > /dev/null 2>&1 ; do
        echo -n '.'
        sleep 3
done | tee -a /root/setup-kolab.log
echo "OK!" | tee -a /root/setup-kolab.log


cat ${SSL_CERTIFICATE} ${SSL_CERTIFICATE_FULLCHAIN} ${SSL_CERTIFICATE_KEY} > /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem
chown cyrus:mail /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem

cp /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem /etc/pki/tls/private/postfix.pem
chown postfix:mail /etc/pki/tls/private/postfix.pem
chmod 655 /etc/pki/tls/private/postfix.pem
systemctl enable --now postfix
systemctl enable --now wallace

# setup imap
if [ -f "/var/lib/imap/db" ]; then
    echo "IMAP directory exists, nothing to do"
else
    echo "Initializing IMAP volume"
    cp -ar /var/lib/imap-bak/* /var/lib/imap/
    systemctl start cyrus-imapd
fi


# Setup httpform auth against kolab
sed -i "s/MECH=.*/MECH=httpform/" /etc/sysconfig/saslauthd

cat > /etc/saslauthd.conf << EOF
httpform_host: services.${APP_DOMAIN}
httpform_port: 8000
httpform_uri: /api/webhooks/cyrus-sasl
httpform_data: %u %r %p
EOF

systemctl restart saslauthd

systemctl enable --now guam
