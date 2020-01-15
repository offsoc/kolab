#!/bin/bash

 . ./settings.sh

(
    echo "dn: cn=PVI,cn=PVS,cn=${hosted_domain_db},cn=ldbm database,cn=plugins,cn=config"
    echo "objectClass: top"
    echo "objectClass: vlvIndex"
    echo "cn: PVI"
    echo "vlvSort: displayname sn givenname cn"
    echo "aci: (targetattr = \"*\") (version 3.0;acl \"Read Access\";allow (read,compare,search)(userdn = \"ldap:///anyone\");)"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

(
    echo "dn: cn=RVI,cn=RVS,cn=${hosted_domain_db},cn=ldbm database,cn=plugins,cn=config"
    echo "objectClass: top"
    echo "objectClass: vlvIndex"
    echo "cn: RVI"
    echo "vlvSort: cn"
    echo "aci: (targetattr = \"*\") (version 3.0;acl \"Read Access\";allow (read,compare,search)(userdn = \"ldap:///anyone\");)"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

(
    echo "dn: cn=GVI,cn=GVS,cn=${hosted_domain_db},cn=ldbm database,cn=plugins,cn=config"
    echo "objectClass: top"
    echo "objectClass: vlvIndex"
    echo "cn: GVI"
    echo "vlvSort: cn"
    echo "aci: (targetattr = \"*\") (version 3.0;acl \"Read Access\";allow (read,compare,search)(userdn = \"ldap:///anyone\");)"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

if [ "${domain_base_dn}" != "cn=kolab,cn=config" ]; then
    (
        echo "dn: cn=DVI,cn=DVS,cn=${domain_db},cn=ldbm database,cn=plugins,cn=config"
        echo "objectClass: top"
        echo "objectClass: vlvIndex"
        echo "cn: DVI"
        echo "vlvSort: associatedDomain"
        echo "aci: (targetattr = \"*\") (version 3.0;acl \"Read Access\";allow (read,compare,search)(userdn = \"ldap:///anyone\");)"
        echo ""
    ) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c
fi
