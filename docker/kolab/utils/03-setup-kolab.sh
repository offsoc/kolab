#!/bin/bash

. ./settings.sh

if [ -f /root/kolab.conf.template ]; then
    eval "echo \"$(cat /root/kolab.conf.template)\"" > /root/kolab.conf.ref
    KOLAB_CONFIG_REF="--config=/root/kolab.conf.ref"
    cp -f ${KOLAB_CONFIG_REF#--config=} /etc/kolab/kolab.conf
fi

CMD="$(which setup-kolab) \
    --default ${LDAP_HOST+--without-ldap} ${KOLAB_CONFIG_REF} \
    --fqdn=kolab.${domain}  \
    --timezone=Europe/Zurich \
    --mysqlhost=${DB_HOST:-127.0.0.1} \
    --mysqlserver=existing \
    --mysqlrootpw=${DB_ROOT_PASSWORD:-Welcome2KolabSystems} \
    --directory-manager-pwd=${LDAP_ADMIN_BIND_PW:-Welcome2KolabSystems}"

echo ${CMD} | tee -a /root/setup-kolab.log
echo -n "Wait for MariaDB container: " | tee -a /root/setup-kolab.log
while ! mysqladmin -u root ping > /dev/null 2>&1 ; do
        echo -n '.'
        sleep 3
done | tee -a /root/setup-kolab.log
echo "OK!" | tee -a /root/setup-kolab.log

if [ ! -z "${LDAP_HOST}" ]; then
    echo -n "Wait for DS389 container: " | tee -a /root/setup-kolab.log
    while ! ldapsearch -h ${LDAP_HOST} -D "${LDAP_ADMIN_BIND_DN}" -w "${LDAP_ADMIN_BIND_PW}" -b "" -s base > /dev/null 2>&1 ; do
            echo -n '.'
            sleep 3
    done | tee -a /root/setup-kolab.log
    echo "OK!" | tee -a /root/setup-kolab.log
fi

${CMD} 2>&1 | tee -a /root/setup-kolab.log

