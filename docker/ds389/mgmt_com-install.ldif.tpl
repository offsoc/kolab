# ${LDAP_ADMIN_ROOT_DN}
dn: ${LDAP_ADMIN_ROOT_DN}
aci: (targetattr = \"carLicense || description || displayName || facsimileTelephoneNumber || homePhone || homePostalAddress || initials || jpegPhoto || l || labeledURI || mobile || o || pager || photo || postOfficeBox || postalAddress || postalCode || preferredDeliveryMethod || preferredLanguage || registeredAddress || roomNumber || secretary || seeAlso || st || street || telephoneNumber || telexNumber || title || userCertificate || userPassword || userSMIMECertificate || x500UniqueIdentifier || kolabDelegate || kolabInvitationPolicy || kolabAllowSMTPSender\") (version 3.0; acl \"Enable self write for common attributes\"; allow (read,compare,search,write)(userdn = \"ldap:///self\");)
aci: (targetattr = \"*\") (version 3.0;acl \"Directory Administrators Group\";allow (all)(groupdn = \"ldap:///cn=Directory Administrators,${LDAP_ADMIN_ROOT_DN}\" or roledn = \"ldap:///cn=kolab-admin,${LDAP_ADMIN_ROOT_DN}\");)
aci: (targetattr=\"*\")(version 3.0; acl \"Configuration Administrators Group\"; allow (all) groupdn=\"ldap:///cn=Configuration Administrators,ou=Groups,ou=TopologyManagement,o=NetscapeRoot\";)
aci: (targetattr=\"*\")(version 3.0; acl \"Configuration Administrator\"; allow (all) userdn=\"ldap:///uid=admin,ou=Administrators,ou=TopologyManagement,o=NetscapeRoot\";)
aci: (targetattr = \"*\")(version 3.0; acl \"SIE Group\"; allow (all) groupdn = \"ldap:///cn=slapd-ldap-k8s,cn=389 Directory Server,cn=Server Group,cn=${FULL_MACHINE_NAME},ou=${DOMAIN},o=NetscapeRoot\";)
aci: (targetattr != \"userPassword\") (version 3.0;acl \"Search Access\";allow (read,compare,search)(userdn = \"ldap:///all\");)
objectClass: top
objectClass: domain

# Directory Administrators, ${DOMAIN}
dn: cn=Directory Administrators,${LDAP_ADMIN_ROOT_DN}
objectClass: top
objectClass: groupofuniquenames
cn: Directory Administrators
uniqueMember: cn=Directory Manager

# Domains definition location ${DOMAIN}
dn: ${LDAP_DOMAIN_BASE_DN}
objectclass: top
objectclass: extensibleobject
ou: Domains
aci: (targetattr = \"*\") (version 3.0;acl \"Kolab Services\";allow (read,compare,search)(userdn = \"ldap:///uid=kolab-service,ou=Special Users,${LDAP_ADMIN_ROOT_DN}\");)

# Groups, ${DOMAIN}
dn: ou=Groups,${LDAP_ADMIN_ROOT_DN}
objectClass: top
objectClass: organizationalunit
ou: Groups

# People, ${DOMAIN}
dn: ou=People,${LDAP_ADMIN_ROOT_DN}
objectClass: top
objectClass: organizationalunit
ou: People

# Resources, ${DOMAIN}
dn: ou=Resources,${LDAP_ADMIN_ROOT_DN}
objectClass: top
objectClass: organizationalunit
ou: Resources

# Shared Folders, ${DOMAIN}
dn: ou=Shared Folders,${LDAP_ADMIN_ROOT_DN}
objectClass: top
objectClass: organizationalunit
ou: Shared Folders

# Special User, ${DOMAIN}
dn: ou=Special Users,${LDAP_ADMIN_ROOT_DN}
objectClass: top
objectClass: organizationalUnit
ou: Special Users
description: Special Administrative Accounts

# Add kolab-admin role
dn: cn=kolab-admin,${LDAP_ADMIN_ROOT_DN}
objectClass: top
objectClass: ldapsubentry
objectClass: nsroledefinition
objectClass: nssimpleroledefinition
objectClass: nsmanagedroledefinition
cn: kolab-admin
description: Kolab Administrator

# cyrus-admin, Special Users, ${DOMAIN}
dn: uid=cyrus-admin,ou=Special Users,${LDAP_ADMIN_ROOT_DN}
sn: Administrator
uid: cyrus-admin
objectClass: top
objectClass: person
objectClass: inetorgperson
objectClass: organizationalperson
givenName: Cyrus
cn: Cyrus Administrator
userPassword: ${IMAP_ADMIN_PASSWORD}

# kolab-service, Special Users, ${DOMAIN}
dn: ${LDAP_SERVICE_BIND_DN}
sn: Service
uid: kolab-service
objectClass: top
objectClass: person
objectClass: inetorgperson
objectClass: organizationalperson
givenName: Kolab
cn: Kolab Service
userPassword: ${LDAP_SERVICE_BIND_PW}
nsIdleTimeout: -1
nsTimeLimit: -1
nsSizeLimit: -1
nsLookThroughLimit: -1

# hosted-kolab-service, Special Users, ${DOMAIN}
dn: ${LDAP_HOSTED_BIND_DN}
objectclass: top
objectclass: inetorgperson
objectclass: person
uid: hosted-kolab-service
cn: Hosted Kolab Service Account
sn: Service Account
givenname: Hosted Kolab
userpassword: ${LDAP_HOSTED_BIND_PW}
nsIdleTimeout: -1
nsTimeLimit: -1
nsSizeLimit: -1
nsLookThroughLimit: -1

# ${DOMAIN}, ${LDAP_DOMAIN_BASE_DN} 
dn: associateddomain=${DOMAIN},${LDAP_DOMAIN_BASE_DN}
objectclass: top
objectclass: domainrelatedobject
associateddomain: ${DOMAIN}
associateddomain: localhost.localdomain
associateddomain: localhost
aci: (targetattr = \"*\")(version 3.0;acl \"Deny Rest\";deny (all)(userdn != \"ldap:///${LDAP_SERVICE_BIND_DN} || ldap:///${LDAP_ADMIN_ROOT_DN}??sub?\(objectclass=*\)\");)
aci: (targetattr = \"*\")(version 3.0;acl \"Deny Hosted Kolab\";deny (all)(userdn = \"ldap:///${LDAP_HOSTED_BIND_DN}\");)

