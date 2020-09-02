# ${HOSTED_DOMAIN}, ${LDAP_DOMAIN_BASE_DN} 
dn: associateddomain=${HOSTED_DOMAIN},${LDAP_DOMAIN_BASE_DN}
objectclass: top
objectclass: domainrelatedobject
objectclass: inetdomain
inetdomainstatus: active
associateddomain: ${HOSTED_DOMAIN}
inetdomainbasedn: ${LDAP_HOSTED_ROOT_DN}

# ${LDAP_HOSTED_ROOT_DN}
dn: ${LDAP_HOSTED_ROOT_DN}
aci: (targetattr=\"carLicense || description || displayName || facsimileTelephoneNumber || homePhone || homePostalAddress || initials || jpegPhoto || labeledURI || mobile || pager || photo || postOfficeBox || postalAddress || postalCode || preferredDeliveryMethod || preferredLanguage || registeredAddress || roomNumber || secretary || seeAlso || st || street || telephoneNumber || telexNumber || title || userCertificate || userPassword || userSMIMECertificate || x500UniqueIdentifier\")(version 3.0; acl \"Enable self write for common attributes\"; allow (write) userdn=\"ldap:///self\";)
aci: (targetattr=\"*\")(version 3.0;acl \"Directory Administrators Group\";allow (all) (groupdn=\"ldap:///cn=Directory Administrators,${LDAP_HOSTED_ROOT_DN}\" or roledn=\"ldap:///cn=kolab-admin,${LDAP_HOSTED_ROOT_DN}\");)
aci: (targetattr=\"*\")(version 3.0; acl \"Configuration Administrators Group\"; allow (all) groupdn=\"ldap:///cn=Configuration Administrators,ou=Groups,ou=TopologyManagement,o=NetscapeRoot\";)
aci: (targetattr=\"*\")(version 3.0; acl \"Configuration Administrator\"; allow (all) userdn=\"ldap:///uid=admin,ou=Administrators,ou=TopologyManagement,o=NetscapeRoot\";)
aci: (targetattr=\"*\")(version 3.0; acl \"SIE Group\"; allow (all) groupdn = \"ldap:///cn=slapd-${DS_INSTANCE_NAME},cn=389 Directory Server,cn=Server Group,cn=${FULL_MACHINE_NAME},ou=${DOMAIN},o=NetscapeRoot\";)
aci: (targetattr=\"*\") (version 3.0;acl \"Search Access\";allow (read,compare,search)(userdn = \"ldap:///${LDAP_HOSTED_ROOT_DN}??sub?(objectclass=*)\");)
aci: (targetattr=\"*\") (version 3.0;acl \"Service Search Access\";allow (read,compare,search)(userdn = \"ldap:///${LDAP_SERVICE_BIND_DN}\");)
objectClass: top
objectClass: domain
dc: ${HOSTED_DOMAIN%.com}

# cn=2fa-user, ${LDAP_HOSTED_ROOT_DN}
dn: cn=2fa-user,${LDAP_HOSTED_ROOT_DN}
cn: 2fa-user
description: 2fa-user role
objectclass: top
objectclass: ldapsubentry
objectclass: nsmanagedroledefinition
objectclass: nsroledefinition
objectclass: nssimpleroledefinition

# cn=activesync-user, ${LDAP_HOSTED_ROOT_DN}
dn: cn=activesync-user,${LDAP_HOSTED_ROOT_DN}
cn: activesync-user
description: activesync-user role
objectclass: top
objectclass: ldapsubentry
objectclass: nsmanagedroledefinition
objectclass: nsroledefinition
objectclass: nssimpleroledefinition

# cn=imap-user, ${LDAP_HOSTED_ROOT_DN}
dn: cn=imap-user,${LDAP_HOSTED_ROOT_DN}
cn: imap-user
description: imap-user role
objectclass: top
objectclass: ldapsubentry
objectclass: nsmanagedroledefinition
objectclass: nsroledefinition
objectclass: nssimpleroledefinition

# ou=Groups, ${LDAP_HOSTED_ROOT_DN}
dn: ou=Groups,${LDAP_HOSTED_ROOT_DN}
ou: Groups
objectClass: top
objectClass: organizationalunit

# ou=People, ${LDAP_HOSTED_ROOT_DN}
dn: ou=People,${LDAP_HOSTED_ROOT_DN}
aci: (targetattr=\"*\") (version 3.0;acl \"Hosted Kolab Services\";allow (all)(userdn = \"ldap:///${LDAP_HOSTED_BIND_DN}\");)
ou: People
objectClass: top
objectClass: organizationalunit

# ou=Resources, ${LDAP_HOSTED_ROOT_DN}
dn: ou=Resources,${LDAP_HOSTED_ROOT_DN}
ou: Resources
objectClass: top
objectClass: organizationalunit

# ou=Shared Folders, ${LDAP_HOSTED_ROOT_DN}
dn: ou=Shared Folders,${LDAP_HOSTED_ROOT_DN}
ou: Shared Folders
objectClass: top
objectClass: organizationalunit

