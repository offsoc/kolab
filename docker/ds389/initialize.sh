#!/bin/bash

#docker exec -i -t kolab-ldap /usr/sbin/dsconf localhost backend create --suffix dc=mgmt,dc=com --be-name mgmt_com

LDAPADD="ldapadd -x -H ldap://127.0.0.1:3389 -D 'cn=Directory Manager' -w 'Welcome2KolabSystems'"

cp template.ldif /tmp/template.ldif
sed -i -e 's/%ds_suffix%/dc=mgmt,dc=com/' /tmp/template.ldif
sed -i -e 's/%rootdn%/cn=Directory Manager/' /tmp/template.ldif

eval "$LDAPADD -f kolab-schema.ldif"
eval "$LDAPADD -f /tmp/template.ldif"
