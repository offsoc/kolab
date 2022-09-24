#!/bin/bash

set -x
# ds
#
#
#labjj
#dscontainer

cat > /tmp/dscreate-config << EOF
[general]
FullMachineName = kolab.mgmt.com
SuiteSpotUserID = dirsrv
SuiteSpotGroup = dirsrv
AdminDomain = mgmt.com
ConfigDirectoryLdapURL = ldap://kolab.mgmt.com:389/o=NetscapeRoot
ConfigDirectoryAdminID = admin
ConfigDirectoryAdminPwd = CzAsObG6KyYTte9
full_machine_name = kolab.mgmt.com

[slapd]
SlapdConfigForMC = Yes
UseExistingMC = 0
ServerPort = 389
ServerIdentifier = kolab
Suffix = dc=mgmt,dc=com
RootDN = cn=Directory Manager
RootDNPwd = Welcome2KolabSystems
ds_bename = mgmt_com
AddSampleEntries = No
instance_name = kolab
root_password = Welcome2KolabSystems
create_suffix_entry = True

[backend-userroot]
suffix = dc=mgmt,dc=com
create_suffix_entry = True

[admin]
Port = 9830
ServerAdminID = admin
ServerAdminPwd = CzAsObG6KyYTte9
EOF


dscreate from-file /tmp/dscreate-config

exec /usr/libexec/dirsrv/dscontainer -r
