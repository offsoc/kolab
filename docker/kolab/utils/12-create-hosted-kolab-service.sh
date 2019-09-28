#!/bin/bash

 . ./settings.sh
(
    echo "dn: uid=hosted-kolab-service,ou=Special Users,${rootdn}"
    echo "objectclass: top"
    echo "objectclass: inetorgperson"
    echo "objectclass: person"
    echo "uid: hosted-kolab-service"
    echo "cn: Hosted Kolab Service Account"
    echo "sn: Service Account"
    echo "givenname: Hosted Kolab"
    echo "userpassword: ${hosted_kolab_service_pw}"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"

