#!/bin/bash

# Disable password checking
cp -av /bin/true /usr/sbin/ds_systemd_ask_password_acl

# Make sure all the relvant folders exist in /ldapdata
mkdir -p /ldapdata/{config,ssca,run}
chmod -R 777 /ldapdata

mkdir -p /var/log/dirsrv/slapd-kolab/
chmod 777 /var/log/dirsrv/slapd-kolab/

mkdir -p /run/dirsrv
chmod 777 /run/dirsrv

mkdir -p /run/lock/dirsrv/slapd-kolab/
chown dirsrv:dirsrv /run/lock/dirsrv/slapd-kolab/
chmod 777 /run/lock/dirsrv/slapd-kolab/

mkdir -p /var/lib/dirsrv/slapd-kolab
chown dirsrv:dirsrv /var/lib/dirsrv/slapd-kolab


if [ -f "/etc/dirsrv/slapd-kolab/dse.ldif" ]; then
    echo "LDAP directory exists, nothing to do"

    # mkdir -p /var/log/dirsrv/slapd-kolab/
    # chmod 777 /var/log/dirsrv/slapd-kolab/
    # systemctl start dirsrv@kolab
    # mkdir /run/dirsrv
    # chmod 777 /run/dirsrv
    # mkdir -p /run/lock/dirsrv/slapd-kolab/
    # chown dirsrv:dirsrv /run/lock/dirsrv/slapd-kolab/
    # chmod 777 /run/lock/dirsrv/slapd-kolab/
    # mkdir -p /var/lib/dirsrv/slapd-kolab
    # chown dirsrv:dirsrv /var/lib/dirsrv/slapd-kolab

    systemctl start dirsrv@kolab
    exit 0
fi

# Used for the graphical console only.
GRAPHICAL_ADMIN_PASSWORD="-22F_EjHut5JCcd"
DS_INSTANCE_NAME="kolab"
DOMAIN="mgmt.com"
FQDN="ldap.mgmt.com"

cat << EOF > /tmp/dscreateinput
[general]
FullMachineName = ldap.mgmt.com
SuiteSpotUserID = dirsrv
SuiteSpotGroup = dirsrv
AdminDomain = mgmt.com
ConfigDirectoryLdapURL = ldap://ldap.mgmt.com:389/o=NetscapeRoot
ConfigDirectoryAdminID = admin
ConfigDirectoryAdminPwd = $GRAPHICAL_ADMIN_PASSWORD
full_machine_name = ldap.mgmt.com

[slapd]
SlapdConfigForMC = Yes
UseExistingMC = 0
ServerPort = 389
ServerIdentifier = kolab
Suffix = $LDAP_ADMIN_ROOT_DN
RootDN = cn=Directory Manager
RootDNPwd = $LDAP_ADMIN_BIND_PW
ds_bename = mgmt_com
AddSampleEntries = No
instance_name = $DS_INSTANCE_NAME
root_password = $LDAP_ADMIN_BIND_PW
create_suffix_entry = True

[backend-userroot]
suffix = $LDAP_ADMIN_ROOT_DN
create_suffix_entry = True

[admin]
Port = 9830
ServerAdminID = admin
ServerAdminPwd = $GRAPHICAL_ADMIN_PASSWORD

EOF
dscreate -v from-file /tmp/dscreateinput

cp /usr/share/dirsrv/data/template.ldif /tmp/templatedata.ldif
sed -i "s/%ds_suffix%/$LDAP_BASE_DN/" /tmp/templatedata.ldif
sed -i "s/%rootdn%/cn=Directory Manager/" /tmp/templatedata.ldif
ldapadd -x -H 'ldap://127.0.0.1:389/' -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/templatedata.ldif


#FIXME in kolab container setup kolab.conf entries


cp /usr/share/doc/kolab-schema/kolab3.ldif /etc/dirsrv/slapd-kolab/schema/99kolab3.ldif

systemctl restart dirsrv.target
systemctl restart dirsrv@kolab
systemctl enable dirsrv.target
systemctl enable dirsrv@kolab



# I'm not sure why we need to create those manually
cat << EOF > /tmp/ldapadd

# Directory Administrators, mgmt.com
dn: cn=Directory Administrators,dc=mgmt,dc=com
objectClass: top
objectClass: groupofuniquenames
cn: Directory Administrators
uniqueMember: cn=Directory Manager

# Groups, mgmt.com
dn: ou=Groups,dc=mgmt,dc=com
objectClass: top
objectClass: organizationalunit
ou: Groups

# People, mgmt.com
dn: ou=People,dc=mgmt,dc=com
objectClass: top
objectClass: organizationalunit
ou: People

# Special Users, mgmt.com
dn: ou=Special Users,dc=mgmt,dc=com
objectClass: top
objectClass: organizationalUnit
ou: Special Users
description: Special Administrative Accounts

# Accounting Managers, Groups, mgmt.com
dn: cn=Accounting Managers,ou=Groups,dc=mgmt,dc=com
objectClass: top
objectClass: groupOfUniqueNames
cn: Accounting Managers
ou: groups
description: People who can manage accounting entries
uniqueMember: cn=Directory Manager

# HR Managers, Groups, mgmt.com
dn: cn=HR Managers,ou=Groups,dc=mgmt,dc=com
objectClass: top
objectClass: groupOfUniqueNames
cn: HR Managers
ou: groups
description: People who can manage HR entries
uniqueMember: cn=Directory Manager

# QA Managers, Groups, mgmt.com
dn: cn=QA Managers,ou=Groups,dc=mgmt,dc=com
objectClass: top
objectClass: groupOfUniqueNames
cn: QA Managers
ou: groups
description: People who can manage QA entries
uniqueMember: cn=Directory Manager

# PD Managers, Groups, mgmt.com
dn: cn=PD Managers,ou=Groups,dc=mgmt,dc=com
objectClass: top
objectClass: groupOfUniqueNames
cn: PD Managers
ou: groups
description: People who can manage engineer entries
uniqueMember: cn=Directory Manager

EOF
ldapadd -x -h 127.0.0.1 -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/ldapadd


## =========== Start of pykolab changes
# Work that pykolab used to do
#
cat << EOF > /tmp/ldapadd
# cyrus-admin, Special Users, mgmt.com
dn: uid=cyrus-admin,ou=Special Users,dc=mgmt,dc=com
objectClass: top
objectClass: person
objectClass: inetorgperson
objectClass: organizationalperson
uid: cyrus-admin
givenName: Cyrus
sn: Administrator
cn: Cyrus Administrator
userPassword: ${IMAP_ADMIN_PW}

# kolab-service, Special Users, mgmt.com
dn: uid=kolab-service,ou=Special Users,dc=mgmt,dc=com
objectClass: top
objectClass: person
objectClass: inetorgperson
objectClass: organizationalperson
uid: kolab-service
givenName: Kolab
sn: Service
cn: Kolab Service
userPassword: ${LDAP_SERVICE_BIND_PW}

# Resources, mgmt.com
dn: ou=Resources,dc=mgmt,dc=com
objectClass: top
objectClass: organizationalunit
ou: Resources

# Shared Folders, mgmt.com
dn: ou=Shared Folders,dc=mgmt,dc=com
objectClass: top
objectClass: organizationalunit
ou: Shared Folders

EOF
ldapadd -x -h 127.0.0.1 -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/ldapadd


cat << EOF > /tmp/ldapadd
dn: cn=kolab,cn=config
cn: kolab
aci: (targetattr = "*") (version 3.0;acl "Kolab Services";allow (read,compare,search)(userdn = "ldap:///uid=kolab-service,ou=Special Users,$LDAP_ADMIN_ROOT_DN");)
objectClass: top
objectClass: extensibleobject
EOF
ldapadd -x -h 127.0.0.1 -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/ldapadd

echo "Adding domain $DOMAIN to list of domains for this deployment"
cat << EOF > /tmp/ldapadd
dn: associateddomain=$DOMAIN,cn=kolab,cn=config
objectClass: top
objectClass: domainrelatedobject
associatedDomain: $DOMAIN, $FQDN, localhost.localdomain, localhost
aci: (targetattr = "*") (version 3.0;acl "Read Access for $DOMAIN Users";allow (read,compare,search)(userdn = "ldap:///$LDAP_ADMIN_ROOT_DN??sub?(objectclass=*)");)
EOF
ldapadd -x -h 127.0.0.1 -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/ldapadd
##TODO
    ## Add inetdomainbasedn in case the configured root dn is not the same as the
    ## standard root dn for the domain name configured
    #if not _input['rootdn'] == utils.standard_root_dn(_input['domain']):
    #    attrs['objectclass'].append('inetdomain')
    #    attrs['inetdomainbasedn'] = _input['rootdn']
    
echo "Disabling anonymous binds"
cat << EOF > /tmp/ldapadd
dn: cn=config
changetype: modify
replace: nsslapd-allow-anonymous-access
nsslapd-allow-anonymous-access: off
EOF
ldapmodify -x -h 127.0.0.1 -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/ldapadd


## TODO: Ensure the uid attribute is unique
## TODO^2: Consider renaming the general "attribute uniqueness to "uid attribute uniqueness"
echo "Enabling attribute uniqueness plugin"
cat << EOF > /tmp/ldapadd
dn: cn=attribute uniqueness,cn=plugins,cn=config
changetype: modify
replace: nsslapd-pluginEnabled
nsslapd-pluginEnabled: on
EOF
ldapmodify -x -h 127.0.0.1 -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/ldapadd

echo "Enabling referential integrity plugin"
cat << EOF > /tmp/ldapadd
dn: cn=referential integrity postoperation,cn=plugins,cn=config
changetype: modify
replace: nsslapd-pluginEnabled
nsslapd-pluginEnabled: on
EOF
ldapmodify -x -h 127.0.0.1 -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/ldapadd

echo "Enabling referential integrity plugin"
cat << EOF > /tmp/ldapadd
dn: cn=referential integrity postoperation,cn=plugins,cn=config
changetype: modify
replace: nsslapd-pluginEnabled
nsslapd-pluginEnabled: on
EOF
ldapmodify -x -h 127.0.0.1 -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/ldapadd

echo "Enabling and configuring account policy plugin"
cat << EOF > /tmp/ldapadd
dn: cn=Account Policy Plugin,cn=plugins,cn=config
changetype: modify
replace: nsslapd-pluginEnabled
nsslapd-pluginEnabled: on

dn: cn=config,cn=Account Policy Plugin,cn=plugins,cn=config
changetype: modify
replace: alwaysrecordlogin
alwaysrecordlogin: yes
-
add: stateattrname
stateattrname: lastLoginTime
-
add: altstateattrname
altstateattrname: createTimestamp
EOF
ldapmodify -x -h 127.0.0.1 -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/ldapadd

echo "Adding the kolab-admin role"
cat << EOF > /tmp/ldapadd
dn: cn=kolab-admin,$LDAP_ADMIN_ROOT_DN
description: Kolab Administrator
objectClass: top
objectClass: ldapsubentry
objectClass: nsroledefinition
objectClass: nssimpleroledefinition
objectClass: nsmanagedroledefinition
cn = kolab-admin
EOF
ldapadd -x -h 127.0.0.1 -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/ldapadd

echo "Setting access control to $LDAP_ADMIN_ROOT_DN"
cat << EOF > /tmp/ldapadd
dn: $LDAP_ADMIN_ROOT_DN
changetype: modify
replace: aci
aci: (targetattr = "carLicense || description || displayName || facsimileTelephoneNumber || homePhone || homePostalAddress || initials || jpegPhoto || l || labeledURI || mobile || o || pager || photo || postOfficeBox || postalAddress || postalCode || preferredDeliveryMethod || preferredLanguage || registeredAddress || roomNumber || secretary || seeAlso || st || street || telephoneNumber || telexNumber || title || userCertificate || userPassword || userSMIMECertificate || x500UniqueIdentifier || kolabDelegate || kolabInvitationPolicy || kolabAllowSMTPSender")(version 3.0; acl "Enable self write for common attributes"; allow (read,compare,search,write)(userdn = "ldap:///self");)
aci: (targetattr = "*")(version 3.0;acl "Directory Administrators Group";allow (all)(groupdn = "ldap:///cn=Directory Administrators,$LDAP_ADMIN_ROOT_DN" or roledn = "ldap:///cn=kolab-admin,$LDAP_ADMIN_ROOT_DN");)
aci: (targetattr="*")(version 3.0; acl "Configuration Administrators Group"; allow (all) groupdn="ldap:///cn=Configuration Administrators,ou=Groups,ou=TopologyManagement,o=NetscapeRoot";)
aci: (targetattr="*")(version 3.0; acl "Configuration Administrator"; allow (all) userdn="ldap:///uid=admin,ou=Administrators,ou=TopologyManagement,o=NetscapeRoot";)
aci: (targetattr = "*")(version 3.0; acl "SIE Group"; allow (all) groupdn = "ldap:///cn=slapd-$DS_INSTANCE_NAME,cn=389 Directory Server,cn=Server Group,cn=$FQDN,ou=$DOMAIN,o=NetscapeRoot";)
aci: (targetattr != "userPassword") (version 3.0;acl "Search Access";allow (read,compare,search)(userdn = "ldap:///all");)')
EOF
ldapadd -x -h 127.0.0.1 -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/ldapadd

## =========== End of pykolab code

# Create hosted kolab service
cat << EOF > /tmp/ldapadd
dn: uid=hosted-kolab-service,ou=Special Users,${LDAP_ADMIN_ROOT_DN}
objectclass: top
objectclass: inetorgperson
objectclass: person
uid: hosted-kolab-service
cn: Hosted Kolab Service Account
sn: Service Account
givenname: Hosted Kolab
userpassword: ${LDAP_HOSTED_BIND_PW}

EOF
ldapadd -x -h 127.0.0.1 -D "cn=Directory Manager" -w "$LDAP_ADMIN_BIND_PW" -f /tmp/ldapadd

export rootdn=$LDAP_ADMIN_ROOT_DN
export domain=$DOMAIN
export domain_db="mgmt_com"
export ldap_host=127.0.0.1
export ldap_binddn=${LDAP_ADMIN_BIND_DN}
export ldap_bindpw=${LDAP_ADMIN_BIND_PW}

export cyrus_admin=${IMAP_ADMIN_LOGIN}
export cyrus_admin_pw=${IMAP_ADMIN_PASSWORD}

export kolab_service_pw=${LDAP_SERVICE_BIND_PW}
export hosted_kolab_service_pw=${LDAP_HOSTED_BIND_PW}

export hosted_domain=${HOSTED_DOMAIN:-"hosted.com"}
export hosted_domain_db=${HOSTED_DOMAIN_DB:-"hosted_com"}
export hosted_domain_rootdn=${LDAP_HOSTED_ROOT_DN:-"dc=hosted,dc=com"}

export domain_base_dn=${LDAP_DOMAIN_BASE_DN:-"ou=Domains,dc=mgmt,dc=com"}


# Create ou domain
(
    echo "dn: ou=Domains,${rootdn}"
    echo "ou: Domains"
    echo "objectClass: top"
    echo "objectClass: organizationalunit"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"

# Create management domain
(
    echo "dn: associateddomain=${domain},${domain_base_dn}"
    echo "aci: (targetattr = \"*\")(version 3.0;acl \"Deny Rest\";deny (all)(userdn != \"ldap:///uid=kolab-service,ou=Special Users,${rootdn} || ldap:///${rootdn}??sub?(objectclass=*)\");)"
    echo "aci: (targetattr = \"*\")(version 3.0;acl \"Deny Hosted Kolab\";deny (all)(userdn = \"ldap:///uid=hosted-kolab-service,ou=Special Users,${rootdn}\");)"
    echo "inetDomainStatus: active"
    echo "objectClass: top"
    echo "objectClass: domainrelatedobject"
    echo "objectClass: inetdomain"
    echo "associatedDomain: ${domain}"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"


# Create hosted domains
(
    echo "dn: associateddomain=${hosted_domain},${domain_base_dn}"
    echo "objectclass: top"
    echo "objectclass: domainrelatedobject"
    echo "objectclass: inetdomain"
    echo "inetdomainstatus: active"
    echo "associateddomain: ${hosted_domain}"
    echo "inetdomainbasedn: ${hosted_domain_rootdn}"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"

(
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
    echo "nsslapd-directory: /var/lib/dirsrv/slapd-${DS_INSTANCE_NAME}/db/$(echo ${hosted_domain} | sed -e 's/\./_/g')"
    echo "nsslapd-dncachememsize: 10485760"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"

(
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
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"

(
    echo "dn: ${hosted_domain_rootdn}"
    echo "aci: (targetattr=\"carLicense || description || displayName || facsimileTelephoneNumber || homePhone || homePostalAddress || initials || jpegPhoto || labeledURI || mobile || pager || photo || postOfficeBox || postalAddress || postalCode || preferredDeliveryMethod || preferredLanguage || registeredAddress || roomNumber || secretary || seeAlso || st || street || telephoneNumber || telexNumber || title || userCertificate || userPassword || userSMIMECertificate || x500UniqueIdentifier\")(version 3.0; acl \"Enable self write for common attributes\"; allow (write) userdn=\"ldap:///self\";)"
    echo "aci: (targetattr =\"*\")(version 3.0;acl \"Directory Administrators Group\";allow (all) (groupdn=\"ldap:///cn=Directory Administrators,${hosted_domain_rootdn}\" or roledn=\"ldap:///cn=kolab-admin,${hosted_domain_rootdn}\");)"
    echo "aci: (targetattr=\"*\")(version 3.0; acl \"Configuration Administrators Group\"; allow (all) groupdn=\"ldap:///cn=Configuration Administrators,ou=Groups,ou=TopologyManagement,o=NetscapeRoot\";)"
    echo "aci: (targetattr=\"*\")(version 3.0; acl \"Configuration Administrator\"; allow (all) userdn=\"ldap:///uid=admin,ou=Administrators,ou=TopologyManagement,o=NetscapeRoot\";)"
    echo "aci: (targetattr = \"*\")(version 3.0; acl \"SIE Group\"; allow (all) groupdn = \"ldap:///cn=slapd-${DS_INSTANCE_NAME},cn=389 Directory Server,cn=Server Group,cn=$FQDN,ou=${domain},o=NetscapeRoot\";)"
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


# Remove cn kolab cn config
(
    echo "associateddomain=${domain},cn=kolab,cn=config"
    echo "cn=kolab,cn=config"
) | ldapdelete -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c


# Remove hosted service access from mgmt domain
(
    echo "dn: associateddomain=${domain},ou=Domains,${rootdn}"
    echo "changetype: modify"
    echo "replace: aci"
    echo "aci: (targetattr = \"*\")(version 3.0;acl \"Deny Rest\";deny (all)(userdn != \"ldap:///uid=kolab-service,ou=Special Users,${rootdn} || ldap:///${rootdn}??sub?(objectclass=*)\");)"
    echo "aci: (targetattr = \"*\")(version 3.0;acl \"Deny Hosted Kolab\";deny (all)(userdn = \"ldap:///uid=hosted-kolab-service,ou=Special Users,${rootdn}\");)"
    echo ""
) | ldapmodify -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}"


# Add alias attribute index
#
export index_attr=alias

(
    echo "dn: cn=${index_attr},cn=index,cn=${hosted_domain_db},cn=ldbm database,cn=plugins,cn=config"
    echo "objectclass: top"
    echo "objectclass: nsindex"
    echo "cn: ${index_attr}"
    echo "nsSystemIndex: false"
    echo "nsindextype: pres"
    echo "nsindextype: eq"
    echo "nsindextype: sub"

) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c


(
    echo "dn: cn=${hosted_domain_db} ${index_attr} index,cn=index,cn=tasks,cn=config"
    echo "objectclass: top"
    echo "objectclass: extensibleObject"
    echo "cn: ${hosted_domain_db} ${index_attr} index"
    echo "nsinstance: ${hosted_domain_db}"
    echo "nsIndexAttribute: ${index_attr}:pres"
    echo "nsIndexAttribute: ${index_attr}:eq"
    echo "nsIndexAttribute: ${index_attr}:sub"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

ldap_complete=0

while [ ${ldap_complete} -ne 1 ]; do
    result=$(
            ldapsearch \
                -x \
                -h "${ldap_host}" \
                -D "${ldap_binddn}" \
                -w "${ldap_bindpw}" \
                -c \
                -LLL \
                -b "cn=${hosted_domain_db} ${index_attr} index,cn=index,cn=tasks,cn=config" \
                '(!(nstaskexitcode=0))' \
                -s base 2>/dev/null
        )
    if [ -z "$result" ]; then
        ldap_complete=1
        echo ""
    else
        echo -n "."
        sleep 1
    fi
done


# Add VLV searches
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



# Add vlv indexes
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

# Run vlv index tasks
(
    echo "dn: cn=PVI,cn=index,cn=tasks,cn=config"
    echo "objectclass: top"
    echo "objectclass: extensibleObject"
    echo "cn: PVI"
    echo "nsinstance: ${hosted_domain_db}"
    echo "nsIndexVLVAttribute: PVI"
    echo ""
) | ldapmodify -a -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

ldap_complete=0

while [ ${ldap_complete} -ne 1 ]; do
    result=$(
            ldapsearch \
                -x \
                -h ${ldap_host} \
                -D "${ldap_binddn}" \
                -w "${ldap_bindpw}" \
                -c \
                -LLL \
                -b "cn=PVI,cn=index,cn=tasks,cn=config" \
                '(!(nstaskexitcode=0))' \
                -s base 2>/dev/null
        )
    if [ -z "$result" ]; then
        ldap_complete=1
        echo ""
    else
        echo -n "."
        sleep 1
    fi
done

(
    echo "dn: cn=RVI,cn=index,cn=tasks,cn=config"
    echo "objectclass: top"
    echo "objectclass: extensibleObject"
    echo "cn: RVI"
    echo "nsinstance: ${hosted_domain_db}"
    echo "nsIndexVLVAttribute: RVI"
    echo ""
) | ldapmodify -a -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

ldap_complete=0

while [ ${ldap_complete} -ne 1 ]; do
    result=$(
            ldapsearch \
                -x \
                -h ${ldap_host} \
                -D "${ldap_binddn}" \
                -w "${ldap_bindpw}" \
                -c \
                -LLL \
                -b "cn=RVI,cn=index,cn=tasks,cn=config" \
                '(!(nstaskexitcode=0))' \
                -s base 2>/dev/null
        )
    if [ -z "$result" ]; then
        ldap_complete=1
        echo ""
    else
        echo -n "."
        sleep 1
    fi
done



(
    echo "dn: cn=GVI,cn=index,cn=tasks,cn=config"
    echo "objectclass: top"
    echo "objectclass: extensibleObject"
    echo "cn: GVI"
    echo "nsinstance: ${hosted_domain_db}"
    echo "nsIndexVLVAttribute: GVI"
    echo ""
) | ldapmodify -a -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

ldap_complete=0

while [ ${ldap_complete} -ne 1 ]; do
    result=$(
            ldapsearch \
                -x \
                -h ${ldap_host} \
                -D "${ldap_binddn}" \
                -w "${ldap_bindpw}" \
                -c \
                -LLL \
                -b "cn=GVI,cn=index,cn=tasks,cn=config" \
                '(!(nstaskexitcode=0))' \
                -s base 2>/dev/null
        )
    if [ -z "$result" ]; then
        ldap_complete=1
        echo ""
    else
        echo -n "."
        sleep 1
    fi
done

if [ "${domain_base_dn}" != "cn=kolab,cn=config" ]; then
    (
        echo "dn: cn=DVI,cn=index,cn=tasks,cn=config"
        echo "objectclass: top"
        echo "objectclass: extensibleObject"
        echo "cn: DVI"
        echo "nsinstance: ${domain_db}"
        echo "nsIndexVLVAttribute: DVI"
        echo ""
    ) | ldapmodify -a -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

    ldap_complete=0

    while [ ${ldap_complete} -ne 1 ]; do
        result=$(
                ldapsearch \
                    -x \
                    -h ${ldap_host} \
                    -D "${ldap_binddn}" \
                    -w "${ldap_bindpw}" \
                    -c \
                    -LLL \
                    -b "cn=DVI,cn=index,cn=tasks,cn=config" \
                    '(!(nstaskexitcode=0))' \
                    -s base 2>/dev/null
            )
        if [ -z "$result" ]; then
            ldap_complete=1
            echo ""
        else
            echo -n "."
            sleep 1
        fi
    done
fi
