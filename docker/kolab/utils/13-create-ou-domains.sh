#!/bin/bash

 . ./settings.sh

(
    echo "dn: ou=Domains,${rootdn}"
    echo "ou: Domains"
    echo "objectClass: top"
    echo "objectClass: organizationalunit"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"
