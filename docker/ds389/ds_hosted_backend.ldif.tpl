dn: cn=\"${LDAP_HOSTED_ROOT_DN}\",cn=mapping tree,cn=config
objectClass: top
objectClass: extensibleObject
objectClass: nsMappingTree
nsslapd-state: backend
cn: ${LDAP_HOSTED_ROOT_DN}
nsslapd-backend: ${HOSTED_DOMAIN_DB}

dn: cn=${HOSTED_DOMAIN_DB},cn=ldbm database,cn=plugins,cn=config
objectClass: top
objectClass: extensibleobject
objectClass: nsbackendinstance
cn: ${HOSTED_DOMAIN_DB}
nsslapd-suffix: ${LDAP_HOSTED_ROOT_DN}
nsslapd-cachesize: -1
nsslapd-cachememsize: 10485760
nsslapd-readonly: off
nsslapd-require-index: off
nsslapd-directory: /var/lib/dirsrv/slapd-${DS_INSTANCE_NAME:-$(hostname -s)}/db/${HOSTED_DOMAIN_DB}
nsslapd-dncachememsize: 10485760

