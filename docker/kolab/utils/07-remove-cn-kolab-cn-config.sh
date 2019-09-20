#!/bin/bash

. ./settings.sh

(
    echo "associateddomain=${domain},cn=kolab,cn=config"
    echo "cn=kolab,cn=config"
) | ldapdelete -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c
