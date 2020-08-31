#!/bin/bash

if [ -f /root/kolab.conf.template ]; then
    eval "echo \"$(cat /root/kolab.conf.template)\"" > /root/kolab.conf.ref
    KOLAB_CONFIG_REF="--config=/root/kolab.conf.ref"
    cp -f ${KOLAB_CONFIG_REF#--config=} /etc/kolab/kolab.conf
fi

setup-kolab \
    --default ${LDAP_HOST+--without-ldap} ${KOLAB_CONFIG_REF} \
    --fqdn=kolab.mgmt.com  \
    --timezone=Europe/Zurich \
    --mysqlhost=${DB_HOST:-127.0.0.1} \
    --mysqlserver=existing \
    --mysqlrootpw=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
    --directory-manager-pwd=${LDAP_ADMIN_BIND_PW:-Welcome2KolabSystems} 2>&1 | tee /root/setup-kolab.log

