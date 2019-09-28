#!/bin/bash

. ./settings.sh

(
    echo "dn: uid=cyrus-admin,ou=Special Users,${rootdn}"
    echo "changetype: modify"
    echo "replace: userpassword"
    echo "userpassword: ${ldap_bindpw}"
    echo ""
) | ldapmodify -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -f -
