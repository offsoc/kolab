#!/bin/bash

 . ./settings.sh

(
    echo "dn: cn=TestResource,ou=Resources,ou=kolab.org,${hosted_domain_rootdn}"
    echo "cn: TestResource"
    echo "owner: uid=jack@kolab.org,ou=People,ou=kolab.org,${hosted_domain_rootdn}"
    echo "kolabTargetFolder: shared/Resources/TestResource@kolab.org"
    echo "mail: resource-confroom-testresource@kolab.org"
    echo "objectClass: top"
    echo "objectClass: kolabsharedfolder"
    echo "objectClass: kolabresource"
    echo "objectClass: mailrecipient"
    echo "kolabFolderType: event"
    echo "kolabInvitationPolicy: ACT_MANUAL"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"
