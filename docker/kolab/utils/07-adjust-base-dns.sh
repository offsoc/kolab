#!/bin/bash

. ./settings.sh

echo "ldap_domain_base_dn: ${domain_base_dn}" >> /etc/imapd.conf

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
    /etc/roundcubemail/kolab_auth.inc.php

sed -i -r \
    -e "s/^search_base = .*$/search_base = ${domain_base_dn}/g" \
    /etc/postfix/ldap/mydestination.cf

systemctl restart cyrus-imapd postfix
