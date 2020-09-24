dn: cn=config
changetype: modify
replace: nsslapd-accesslog-logging-enabled
nsslapd-accesslog-logging-enabled: ${DS389_ACCESSLOG:-on}

dn: cn=config
changetype: modify
replace: nsslapd-auditlog-logging-enabled
nsslapd-auditlog-logging-enabled: ${DS389_AUDITLOG:-on}

dn: cn=config
changetype: modify
replace: nsslapd-sizelimit
nsslapd-sizelimit: -1

dn: cn=config
changetype: modify
replace: nsslapd-idletimeout
nsslapd-idletimeout: 0

dn: cn=config
changetype: modify
replace: nsslapd-timelimit
nsslapd-timelimit: -1

dn: cn=config
changetype: modify
replace: nsslapd-lookthroughlimit
nsslapd-lookthroughlimit: -1

dn: cn=config
changetype: modify
replace: nsslapd-allow-anonymous-access
nsslapd-allow-anonymous-access: rootdse

dn: cn=alias,cn=default indexes,cn=config,cn=ldbm database,cn=plugins,cn=config
changetype: add
objectClass: top
objectClass: nsIndex
cn: alias
nsSystemIndex: false
nsIndexType: pres
nsIndexType: eq
nsIndexType: sub

dn: cn=mailAlternateAddress,cn=default indexes,cn=config,cn=ldbm database,cn=plugins,cn=config
changetype: modify
add: nsIndexType
nsIndexType: pres
nsIndexType: sub

dn: cn=associateddomain,cn=default indexes,cn=config,cn=ldbm database,cn=plugins,cn=config
changetype: add
objectclass: top
objectclass: nsindex
cn: associateddomain
nsSystemIndex: false
nsindextype: pres
nsindextype: eq

dn: cn=ACL Plugin,cn=plugins,cn=config
changetype: modify
replace: nsslapd-aclpb-max-selected-acls
nsslapd-aclpb-max-selected-acls: 8192

dn: cn=7-bit check,cn=plugins,cn=config
changetype: modify
replace: nsslapd-pluginEnabled
nsslapd-pluginEnabled: off

dn: cn=attribute uniqueness,cn=plugins,cn=config
changetype: modify
replace: nsslapd-pluginEnabled
nsslapd-pluginEnabled: on

dn: cn=referential integrity postoperation,cn=plugins,cn=config
changetype: modify
replace: nsslapd-pluginEnabled
nsslapd-pluginEnabled: on

dn: cn=Account Policy Plugin,cn=plugins,cn=config
changetype: modify
replace: nsslapd-pluginEnabled
nsslapd-pluginEnabled: on

dn: cn=Account Policy Plugin,cn=plugins,cn=config
changetype: modify
replace: nsslapd-pluginarg0
nsslapd-pluginarg0: cn=config,cn=Account Policy Plugin,cn=plugins,cn=config

dn: cn=config,cn=Account Policy Plugin,cn=plugins,cn=config
changetype: modify
replace: alwaysrecordlogin
alwaysrecordlogin: yes

dn: cn=config,cn=Account Policy Plugin,cn=plugins,cn=config
changetype: modify
replace: stateattrname
stateattrname: lastLoginTime

dn: cn=config,cn=Account Policy Plugin,cn=plugins,cn=config
changetype: modify
replace: altstateattrname
altstateattrname: createTimestamp

