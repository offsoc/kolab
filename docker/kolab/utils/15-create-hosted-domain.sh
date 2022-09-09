#!/bin/bash

. ./settings.sh

(
    echo "dn: associateddomain=${hosted_domain},${domain_base_dn}"
    echo "objectclass: top"
    echo "objectclass: domainrelatedobject"
    echo "objectclass: inetdomain"
    echo "inetdomainstatus: active"
    echo "associateddomain: ${hosted_domain}"
    echo "inetdomainbasedn: ${hosted_domain_rootdn}"
    echo ""

    #On centos7
    #echo "dn: cn=$(echo ${hosted_domain_rootdn} | sed -e 's/=/\\3D/g' -e 's/,/\\2D/g'),cn=mapping tree,cn=config"
    #On centos8
    echo "dn: cn=\"${hosted_domain_rootdn}\",cn=mapping tree,cn=config"
    echo "objectClass: top"
    echo "objectClass: extensibleObject"
    echo "objectClass: nsMappingTree"
    echo "nsslapd-state: backend"
    echo "cn: ${hosted_domain_rootdn}"
    echo "nsslapd-backend: $(echo ${hosted_domain} | sed -e 's/\./_/g')"
    echo ""

    echo "dn: cn=$(echo ${hosted_domain} | sed -e 's/\./_/g'),cn=ldbm database,cn=plugins,cn=config"
    echo "objectClass: top"
    echo "objectClass: extensibleobject"
    echo "objectClass: nsbackendinstance"
    echo "cn: $(echo ${hosted_domain} | sed -e 's/\./_/g')"
    echo "nsslapd-suffix: ${hosted_domain_rootdn}"
    echo "nsslapd-cachesize: -1"
    echo "nsslapd-cachememsize: 10485760"
    echo "nsslapd-readonly: off"
    echo "nsslapd-require-index: off"
    echo "nsslapd-directory: /var/lib/dirsrv/slapd-${DS_INSTANCE_NAME:-$(hostname -s)}/db/$(echo ${hosted_domain} | sed -e 's/\./_/g')"
    echo "nsslapd-dncachememsize: 10485760"
    echo ""

) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"

(
    echo "dn: ${hosted_domain_rootdn}"
    echo "aci: (targetattr=\"carLicense || description || displayName || facsimileTelephoneNumber || homePhone || homePostalAddress || initials || jpegPhoto || labeledURI || mobile || pager || photo || postOfficeBox || postalAddress || postalCode || preferredDeliveryMethod || preferredLanguage || registeredAddress || roomNumber || secretary || seeAlso || st || street || telephoneNumber || telexNumber || title || userCertificate || userPassword || userSMIMECertificate || x500UniqueIdentifier\")(version 3.0; acl \"Enable self write for common attributes\"; allow (write) userdn=\"ldap:///self\";)"
    echo "aci: (targetattr =\"*\")(version 3.0;acl \"Directory Administrators Group\";allow (all) (groupdn=\"ldap:///cn=Directory Administrators,${hosted_domain_rootdn}\" or roledn=\"ldap:///cn=kolab-admin,${hosted_domain_rootdn}\");)"
    echo "aci: (targetattr=\"*\")(version 3.0; acl \"Configuration Administrators Group\"; allow (all) groupdn=\"ldap:///cn=Configuration Administrators,ou=Groups,ou=TopologyManagement,o=NetscapeRoot\";)"
    echo "aci: (targetattr=\"*\")(version 3.0; acl \"Configuration Administrator\"; allow (all) userdn=\"ldap:///uid=admin,ou=Administrators,ou=TopologyManagement,o=NetscapeRoot\";)"
    echo "aci: (targetattr = \"*\")(version 3.0; acl \"SIE Group\"; allow (all) groupdn = \"ldap:///cn=slapd-$(hostname -s),cn=389 Directory Server,cn=Server Group,cn=$(hostname -f),ou=${domain},o=NetscapeRoot\";)"
    echo "aci: (targetattr = \"*\") (version 3.0;acl \"Search Access\";allow (read,compare,search)(userdn = \"ldap:///${hosted_domain_rootdn}??sub?(objectclass=*)\");)"
    echo "aci: (targetattr = \"*\") (version 3.0;acl \"Service Search Access\";allow (read,compare,search)(userdn = \"ldap:///uid=kolab-service,ou=Special Users,${rootdn}\");)"
    echo "objectClass: top"
    echo "objectClass: domain"
    echo "dc: $(echo ${hosted_domain} | cut -d'.' -f 1)"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"

(
    for role in "2fa-user" "activesync-user" "imap-user"; do
        echo "dn: cn=${role},${hosted_domain_rootdn}"
        echo "cn: ${role}"
        echo "description: ${role} role"
        echo "objectclass: top"
        echo "objectclass: ldapsubentry"
        echo "objectclass: nsmanagedroledefinition"
        echo "objectclass: nsroledefinition"
        echo "objectclass: nssimpleroledefinition"
        echo ""
    done

    echo "dn: ou=Groups,${hosted_domain_rootdn}"
    echo "ou: Groups"
    echo "objectClass: top"
    echo "objectClass: organizationalunit"
    echo ""

    echo "dn: ou=People,${hosted_domain_rootdn}"
    echo "aci: (targetattr = \"*\") (version 3.0;acl \"Hosted Kolab Services\";allow (all)(userdn = \"ldap:///uid=hosted-kolab-service,ou=Special Users,${rootdn}\");)"
    echo "ou: People"
    echo "objectClass: top"
    echo "objectClass: organizationalunit"
    echo ""

    echo "dn: ou=Special Users,${hosted_domain_rootdn}"
    echo "ou: Special Users"
    echo "objectClass: top"
    echo "objectClass: organizationalunit"
    echo ""

    echo "dn: ou=Resources,${hosted_domain_rootdn}"
    echo "ou: Resources"
    echo "objectClass: top"
    echo "objectClass: organizationalunit"
    echo ""

    echo "dn: ou=Shared Folders,${hosted_domain_rootdn}"
    echo "ou: Shared Folders"
    echo "objectClass: top"
    echo "objectClass: organizationalunit"
    echo ""

    echo "dn: uid=cyrus-admin,ou=Special Users,${hosted_domain_rootdn}"
    echo "sn: Administrator"
    echo "uid: cyrus-admin"
    echo "objectClass: top"
    echo "objectClass: person"
    echo "objectClass: inetorgperson"
    echo "objectClass: organizationalperson"
    echo "givenName: Cyrus"
    echo "cn: Cyrus Administrator"
    echo ""

) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"
