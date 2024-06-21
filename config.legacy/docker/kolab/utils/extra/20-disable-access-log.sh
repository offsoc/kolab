#!/bin/bash

 . ./settings.sh

(
    echo "dn: cn=config"
    echo "changetype: modify"
    echo "replace: nsslapd-accesslog-logging-enabled"
    echo "nsslapd-accesslog-logging-enabled: off"
    echo ""
) | ldapmodify -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

