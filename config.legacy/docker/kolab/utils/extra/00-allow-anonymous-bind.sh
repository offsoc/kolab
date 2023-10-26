#!/bin/bash

 . ./settings.sh

(
    echo "dn: cn=config"
    echo "changetype: modify"
    echo "replace: nsslapd-allow-anonymous-access"
    echo "nsslapd-allow-anonymous-access: on"
    echo ""
) | ldapmodify -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"
