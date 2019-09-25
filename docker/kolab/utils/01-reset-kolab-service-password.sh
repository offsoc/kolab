#!/bin/bash

. ./settings.sh

(
    echo "dn: uid=kolab-service,ou=Special Users,${rootdn}"
    echo "changetype: modify"
    echo "replace: userpassword"
    echo "userpassword: ${ldap_bindpw}"
    echo ""
) | ldapmodify -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"

oldpw=$(grep ^service_bind_pw /etc/kolab/kolab.conf | awk '{print $3}')

sed -i -r \
    -e "s/${oldpw}/${ldap_bindpw}/g" \
    $(grep -rn ${oldpw} /etc/ | awk -F':' '{print $1}' | sort -u)
