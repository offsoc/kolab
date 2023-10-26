#!/bin/bash

. ./settings.sh

(
    echo "dn: uid=cyrus-admin,ou=Special Users,${rootdn}"
    echo "changetype: modify"
    echo "replace: userpassword"
    echo "userpassword: ${cyrus_admin_pw}"
    echo ""
) | ldapmodify -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"

oldpw=$(grep ^admin_password /etc/kolab/kolab.conf | awk '{print $3}')

sed -i -r \
    -e "s/${oldpw}/${cyrus_admin_pw}/g" \
    /etc/kolab/kolab.conf

systemctl restart kolabd wallace

