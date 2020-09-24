#!/bin/bash

. ./settings.sh

(
    echo "dn: associateddomain=${domain},ou=Domains,${rootdn}"
    echo "changetype: modify"
    echo "replace: aci"
    echo "aci: (targetattr = \"*\")(version 3.0;acl \"Deny Rest\";deny (all)(userdn != \"ldap:///uid=kolab-service,ou=Special Users,${rootdn} || ldap:///${rootdn}??sub?(objectclass=*)\");)"
    echo "aci: (targetattr = \"*\")(version 3.0;acl \"Deny Hosted Kolab\";deny (all)(userdn = \"ldap:///uid=hosted-kolab-service,ou=Special Users,${rootdn}\");)"
    echo ""
) | ldapmodify -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"
