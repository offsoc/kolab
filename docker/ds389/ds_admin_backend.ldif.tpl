dn: cn=\"${LDAP_ADMIN_ROOT_DN}\",cn=mapping tree,cn=config
objectClass: top
objectClass: extensibleObject
objectClass: nsMappingTree
cn: ${LDAP_ADMIN_ROOT_DN}
nsslapd-state: backend
nsslapd-backend: ${DOMAIN_DB}

dn: cn=${DOMAIN_DB},cn=ldbm database,cn=plugins,cn=config
objectClass: top
objectClass: extensibleObject
objectClass: nsBackendInstance
cn: ${DOMAIN_DB}
nsslapd-suffix: ${LDAP_ADMIN_ROOT_DN}
nsslapd-cachesize: -1
nsslapd-cachememsize: 10485760
nsslapd-readonly: off
nsslapd-require-index: off
nsslapd-directory: /var/lib/dirsrv/slapd-${DS_INSTANCE_NAME:-$(hostname -s)}/db/${DOMAIN_DB}
nsslapd-dncachememsize: 10485760
