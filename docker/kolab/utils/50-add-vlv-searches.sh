#!/bin/bash

 . ./settings.sh

(
    echo "dn: cn=PVS,cn=${hosted_domain_db},cn=ldbm database,cn=plugins,cn=config"
    echo "objectClass: top"
    echo "objectClass: vlvSearch"
    echo "cn: PVS"
    echo "vlvBase: ${hosted_domain_rootdn}"
    echo "vlvScope: 2"
    echo "vlvFilter: (objectclass=inetorgperson)"
    echo "aci: (targetattr = \"*\") (version 3.0;acl \"Read Access\";allow (read,compare,search)(userdn = \"ldap:///anyone\");)"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

(
    echo "dn: cn=RVS,cn=${hosted_domain_db},cn=ldbm database,cn=plugins,cn=config"
    echo "objectClass: top"
    echo "objectClass: vlvSearch"
    echo "cn: RVS"
    echo "vlvBase: ${hosted_domain_rootdn}"
    echo "vlvScope: 2"
    echo "vlvFilter: (|(&(objectclass=kolabsharedfolder)(kolabfoldertype=event)(mail=*))(objectclass=groupofuniquenames)(objectclass=groupofurls))"
    echo "aci: (targetattr = \"*\") (version 3.0;acl \"Read Access\";allow (read,compare,search)(userdn = \"ldap:///anyone\");)"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

(
    echo "dn: cn=GVS,cn=${hosted_domain_db},cn=ldbm database,cn=plugins,cn=config"
    echo "objectClass: top"
    echo "objectClass: vlvSearch"
    echo "cn: GVS"
    echo "vlvBase: ${hosted_domain_rootdn}"
    echo "vlvScope: 2"
    echo "vlvFilter: (|(objectclass=groupofuniquenames)(objectclass=groupofurls))"
    echo "aci: (targetattr = \"*\") (version 3.0;acl \"Read Access\";allow (read,compare,search)(userdn = \"ldap:///anyone\");)"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

if [ "${domain_base_dn}" != "cn=kolab,cn=config" ]; then
    (
        echo "dn: cn=DVS,cn=${domain_db},cn=ldbm database,cn=plugins,cn=config"
        echo "objectClass: top"
        echo "objectClass: vlvSearch"
        echo "cn: DVS"
        echo "vlvBase: ${domain_base_dn}"
        echo "vlvScope: 2"
        echo "vlvFilter: (objectclass=domainrelatedobject)"
        echo "aci: (targetattr = \"*\") (version 3.0;acl \"Read Access\";allow (read,compare,search)(userdn = \"ldap:///anyone\");)"
        echo ""
    ) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c
fi
