[General]
FullMachineName = ${FULL_MACHINE_NAME}
SuiteSpotUserID = nobody
SuiteSpotGroup = nobody
AdminDomain = ${DOMAIN}
StrictHostCheck = ${STRICT_HOST_CHECK}
ConfigDirectoryLdapURL = ldap://${DS_INSTANCE_NAME}:389/o=NetscapeRoot
ConfigDirectoryAdminID = admin
ConfigDirectoryAdminPwd = ${LDAP_ADMIN_BIND_PW}

[slapd]
start_server = 0
SlapdConfigForMC = Yes
UseExistingMC = 0
ServerPort = 389
ServerIdentifier = ${DS_INSTANCE_NAME}
RootDN = ${LDAP_ADMIN_BIND_DN}
RootDNPwd = ${LDAP_ADMIN_BIND_PW}
AddSampleEntries = No
SchemaFile = /99kolab-schema.ldif
## InstallLdifFile = /ds_install.ldif
ConfigFile = /ds_adjustments.ldif
ds_bename = ${DOMAIN_DB}
Suffix = ${LDAP_ADMIN_ROOT_DN}
ConfigFile = /ds_admin_backend.ldif
ConfigFile = /ds_hosted_backend.ldif

