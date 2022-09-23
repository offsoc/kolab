#!/bin/bash

# Replace localhost
sed -i -e "/hosts/s/localhost/${LDAP_HOST}/" /etc/iRony/dav.inc.php
sed -i -e "/host/s/localhost/${LDAP_HOST}/g" \
       -e "/fbsource/s/localhost/${IMAP_HOST}/g" /etc/kolab-freebusy/config.ini
#sed -i -e "s/server_host.*/server_host = ${LDAP_HOST}/g" /etc/postfix/ldap/*
sed -i -e "/password_ldap_host/s/localhost/${LDAP_HOST}/" /etc/roundcubemail/password.inc.php
sed -i -e "/hosts/s/localhost/${LDAP_HOST}/" /etc/roundcubemail/kolab_auth.inc.php
sed -i -e "s#.*db_dsnw.*#    \$config['db_dsnw'] = 'mysql://${DB_RC_USERNAME}:${DB_RC_PASSWORD}@${DB_HOST}/roundcube';#" \
       -e "/default_host/s|= .*$|= 'ssl://${IMAP_HOST}';|" \
       -e "/default_port/s|= .*$|= ${IMAP_PORT};|" \
       -e "/smtp_server/s|= .*$|= 'tls://${MAIL_HOST}';|" \
       -e "/smtp_port/s/= .*$/= ${MAIL_PORT};/" \
       -e "/hosts/s/localhost/${LDAP_HOST}/" /etc/roundcubemail/config.inc.php
sed -i -e "/hosts/s/localhost/${LDAP_HOST}/" /etc/roundcubemail/calendar.inc.php


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
    /etc/kolab-freebusy/config.ini \
    /etc/postfix/ldap/*.cf \
    /etc/roundcubemail/config.inc.php \
    /etc/roundcubemail/calendar.inc.php \
    /etc/roundcubemail/kolab_auth.inc.php

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


sed -i -r \
    -e "s/'vlv'(\s+)=> false,/'vlv'\1=> true,/g" \
    -e "s/'vlv_search'(\s+)=> false,/'vlv_search'\1=> true,/g" \
    -e "s/inetOrgPerson/inetorgperson/g" \
    -e "s/kolabInetOrgPerson/inetorgperson/g" \
    /etc/roundcubemail/*.inc.php


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



sed -i -r -e "s|$config\['kolab_files_url'\] = .*$|$config['kolab_files_url'] = 'https://' \. \$_SERVER['HTTP_HOST'] . '/chwala/';|g" /etc/roundcubemail/kolab_files.inc.php

sed -i -r -e "s|$config\['kolab_invitation_calendars'\] = .*$|$config['kolab_invitation_calendars'] = true;|g" /etc/roundcubemail/calendar.inc.php

sed -i -r -e "/^.*'contextmenu',$/a 'enigma'," /etc/roundcubemail/config.inc.php

sed -i -r -e "s|$config\['enigma_passwordless'\] = .*$|$config['enigma_passwordless'] = true;|g" /etc/roundcubemail/enigma.inc.php
sed -i -r -e "s|$config\['enigma_multihost'\] = .*$|$config['enigma_multihost'] = true;|g" /etc/roundcubemail/enigma.inc.php

echo "\$config['enigma_woat'] = true;" >> /etc/roundcubemail/enigma.inc.php

# Run it over haproxy then nginx for 2fa. We need to use startls because otherwise the proxy protocol doesn't work.
sed -i -r -e "s|$config\['default_host'\] = .*$|$config['default_host'] = 'tls://haproxy';|g" /etc/roundcubemail/config.inc.php
sed -i -r -e "s|$config\['default_port'\] = .*$|$config['default_port'] = 145;|g" /etc/roundcubemail/config.inc.php

# So we can just append
sed -i "s/?>//g" /etc/roundcubemail/config.inc.php

# Enable the PROXY protocol
cat << EOF >> /etc/roundcubemail/config.inc.php
    \$config['imap_conn_options'] = Array(
            'ssl' => Array(
                    'verify_peer_name' => false,
                    'verify_peer' => false,
                    'allow_self_signed' => true
                ),
            'proxy_protocol' => 2
        );
    \$config['proxy_whitelist'] = array('127.0.0.1');
EOF

echo "?>" >> /etc/roundcubemail/config.inc.php
