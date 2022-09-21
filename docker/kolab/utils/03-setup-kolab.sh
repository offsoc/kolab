#!/bin/bash

. ./settings.sh


echo ${CMD} | tee -a /root/setup-kolab.log
echo -n "Wait for MariaDB container: " | tee -a /root/setup-kolab.log
while ! mysqladmin -u root ping > /dev/null 2>&1 ; do
        echo -n '.'
        sleep 3
done | tee -a /root/setup-kolab.log
echo "OK!" | tee -a /root/setup-kolab.log


# if [ -f /root/kolab.conf.template ]; then
#     eval "echo \"$(cat /root/kolab.conf.template)\"" > /root/kolab.conf.ref
#     KOLAB_CONFIG_REF="--config=/root/kolab.conf.ref"
#     cp -f ${KOLAB_CONFIG_REF#--config=} /etc/kolab/kolab.conf
# fi

if [ -d "/var/lib/dirsrv/slapd-kolab/" ]; then
    echo "LDAP directory exists"
    #FIXME not implemented
    exit 1
else
    echo "LDAP directory does not exist"
    CMD="$(which setup-kolab) ldap \
        --default ${LDAP_HOST} \
        --fqdn=kolab.${domain}  \
        --directory-manager-pwd=${LDAP_ADMIN_BIND_PW:-Welcome2KolabSystems}"
    ${CMD} 2>&1 | tee -a /root/setup-kolab.log
fi

if [ ! -z "${LDAP_HOST}" ]; then
    echo -n "Wait for DS389 container: " | tee -a /root/setup-kolab.log
    while ! ldapsearch -h ${LDAP_HOST} -D "${LDAP_ADMIN_BIND_DN}" -w "${LDAP_ADMIN_BIND_PW}" -b "" -s base > /dev/null 2>&1 ; do
            echo -n '.'
            sleep 3
    done | tee -a /root/setup-kolab.log
    echo "OK!" | tee -a /root/setup-kolab.log
fi


cat > /tmp/kolab-setup-my.cnf << EOF
[client]
host=${DB_HOST}
user=root
password=${DB_ROOT_PASSWORD}
EOF


CMD="$(which setup-kolab) mta \
    --default"
${CMD} 2>&1 | tee -a /root/setup-kolab.log



CMD="$(which setup-kolab) php \
    --default \
    --timezone=Europe/Zurich"
${CMD} 2>&1 | tee -a /root/setup-kolab.log

# setup imap
systemctl stop saslauthd
systemctl start kolab-saslauthd
systemctl enable kolab-saslauthd
#Setup guam
systemctl start guam
systemctl enable guam


#TODO just add /etc/kolab-freebusy/
# CMD="$(which setup-kolab) freebusy \
#     --default"
# ${CMD} 2>&1 | tee -a /root/setup-kolab.log

cat > /tmp/kolab-setup-my.cnf << EOF
[client]
host=${DB_HOST}
user=root
password=${DB_ROOT_PASSWORD}
EOF

CMD="$(which setup-kolab) roundcube \
    --default"
${CMD} 2>&1 | tee -a /root/setup-kolab.log

cat > /tmp/kolab-setup-my.cnf << EOF
[client]
host=${DB_HOST}
user=root
password=${DB_ROOT_PASSWORD}
EOF

CMD="$(which setup-kolab) syncroton \
    --default"
${CMD} 2>&1 | tee -a /root/setup-kolab.log

