#!/bin/bash

. ./settings.sh

(
    echo "dn: uid=kolab-service,ou=Special Users,${rootdn}"
    echo "changetype: modify"
    echo "replace: userpassword"
    echo "userpassword: ${ldap_bindpw}"
    echo ""
) | ldapmodify -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"

sed -i -r -e "s/^service_bind_pw = .*$/service_bind_pw = ${ldap_bindpw}/g" /etc/kolab/kolab.conf
