#!/bin/bash

cat ${SSL_CERTIFICATE} ${SSL_CERTIFICATE_FULLCHAIN} ${SSL_CERTIFICATE_KEY} > /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem
chown cyrus:mail /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem

cp /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem /etc/pki/tls/private/postfix.pem
chown postfix:mail /etc/pki/tls/private/postfix.pem
chmod 655 /etc/pki/tls/private/postfix.pem

sed -i "s/smtpd_tls_key_file =.*/smtpd_tls_key_file = \/etc\/pki\/tls\/private\/postfix.pem/" /etc/postfix/main.cf
sed -i "s/smtpd_tls_cert_file =.*/smtpd_tls_cert_file = \/etc\/pki\/tls\/private\/postfix.pem/" /etc/postfix/main.cf

# Remove the submission block, by matching from submission until the next empty line
sed -i -e '/submission          inet/,/^$/d' /etc/postfix/master.cf

# Insert a new submission block with a modified port
cat >> /etc/postfix/master.cf << EOF
127.0.0.1:10587     inet        n       -       n       -       -       smtpd
    -o cleanup_service_name=cleanup_submission
    -o syslog_name=postfix/submission
    #-o smtpd_tls_security_level=encrypt
    -o smtpd_sasl_auth_enable=yes
    -o smtpd_sasl_authenticated_header=yes
    -o smtpd_client_restrictions=permit_sasl_authenticated,reject
    -o smtpd_data_restrictions=\$submission_data_restrictions
    -o smtpd_recipient_restrictions=\$submission_recipient_restrictions
    -o smtpd_sender_restrictions=\$submission_sender_restrictions

127.0.0.1:10465     inet        n       -       n       -       -       smtpd
    -o cleanup_service_name=cleanup_submission
    -o rewrite_service_name=rewrite_submission
    -o syslog_name=postfix/smtps
    -o mydestination=
    -o local_recipient_maps=
    -o relay_domains=
    -o relay_recipient_maps=
    #-o smtpd_tls_wrappermode=yes
    -o smtpd_sasl_auth_enable=yes
    -o smtpd_sasl_authenticated_header=yes
    -o smtpd_client_restrictions=permit_sasl_authenticated,reject
    -o smtpd_sender_restrictions=\$submission_sender_restrictions
    -o smtpd_recipient_restrictions=\$submission_recipient_restrictions
    -o smtpd_data_restrictions=\$submission_data_restrictions
EOF

systemctl restart postfix
