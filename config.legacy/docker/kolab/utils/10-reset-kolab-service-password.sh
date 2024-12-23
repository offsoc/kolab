#!/bin/bash

. ./settings.sh

(
    echo "dn: uid=kolab-service,ou=Special Users,${rootdn}"
    echo "changetype: modify"
    echo "replace: userpassword"
    echo "userpassword: ${kolab_service_pw}"
    echo ""
) | ldapmodify -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"

oldpw=$(grep ^service_bind_pw /etc/kolab/kolab.conf | awk '{print $3}')

sed -i -r \
    -e "s/${oldpw}/${kolab_service_pw}/g" \
    $(grep -rn -- ${oldpw} /etc/ | awk -F':' '{print $1}' | sort -u)

systemctl restart \
    cyrus-imapd \
    kolabd \
    kolab-saslauthd \
    postfix
