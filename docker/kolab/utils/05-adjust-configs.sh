#!/bin/bash

#sed -i -e "s/server_host.*/server_host = ${LDAP_HOST}/g" /etc/postfix/ldap/*
. ./settings.sh

#Adjust basedn
sed -i -r \
    -e "s/(\s+)base => '.*',$/\1base => '${hosted_domain_rootdn}',/g" \
    -e "/\\\$mydomain = / a\
\$myhostname = '${HOSTNAME:-kolab}.${DOMAIN:-mgmt.com}';" \
    -e "s/^base_dn = .*$/base_dn = ${hosted_domain_rootdn}/g" \
    -e "s/^search_base = .*$/search_base = ${hosted_domain_rootdn}/g" \
    -e "s/(\s+)'base_dn'(\s+)=> '.*',/\1'base_dn'\2=> '${hosted_domain_rootdn}',/g" \
    -e "s/(\s+)'search_base_dn'(\s+)=> '.*',/\1'search_base_dn'\2=> '${hosted_domain_rootdn}',/g" \
    -e "s/(\s+)'user_specific'(\s+)=> false,/\1'user_specific'\2=> true,/g" \
    /etc/amavisd/amavisd.conf \
    /etc/postfix/ldap/*.cf

sed -i -r \
    -e "s/^search_base = .*$/search_base = ${domain_base_dn}/g" \
    /etc/postfix/ldap/mydestination.cf


#Disable amavisd
postconf -e content_filter='smtp-wallace:[127.0.0.1]:10026'

systemctl stop amavisd
systemctl disable amavisd

systemctl stop clamd@amavisd
systemctl disable clamd@amavisd


# Change port numbers
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


# Adjust postfix

# new: (inetdomainstatus:1.2.840.113556.1.4.803:=1)
# active: (inetdomainstatus:1.2.840.113556.1.4.803:=2)
# suspended: (inetdomainstatus:1.2.840.113556.1.4.803:=4)
# deleted: (inetdomainstatus:1.2.840.113556.1.4.803:=8)
# confirmed: (inetdomainstatus:1.2.840.113556.1.4.803:=16)
# verified: (inetdomainstatus:1.2.840.113556.1.4.803:=32)
# ready: (inetdomainstatus:1.2.840.113556.1.4.803:=64)

sed -i -r \
    -e 's/^query_filter.*$/query_filter = (\&(associatedDomain=%s)(inetdomainstatus:1.2.840.113556.1.4.803:=18)(!(inetdomainstatus:1.2.840.113556.1.4.803:=4)))/g' \
    /etc/postfix/ldap/mydestination.cf

# new: (inetuserstatus:1.2.840.113556.1.4.803:=1)
# active: (inetuserstatus:1.2.840.113556.1.4.803:=2)
# suspended: (inetuserstatus:1.2.840.113556.1.4.803:=4)
# deleted: (inetuserstatus:1.2.840.113556.1.4.803:=8)
# ldapready: (inetuserstatus:1.2.840.113556.1.4.803:=16)
# imapready: (inetuserstatus:1.2.840.113556.1.4.803:=32)

sed -i -r \
    -e 's/^query_filter.*$/query_filter = (\&(|(mail=%s)(alias=%s))(|(objectclass=kolabinetorgperson)(|(objectclass=kolabgroupofuniquenames)(objectclass=kolabgroupofurls))(|(|(objectclass=groupofuniquenames)(objectclass=groupofurls))(objectclass=kolabsharedfolder))(objectclass=kolabsharedfolder))(!(inetuserstatus:1.2.840.113556.1.4.803:=4)))/g' \
    /etc/postfix/ldap/local_recipient_maps.cf

systemctl restart postfix
