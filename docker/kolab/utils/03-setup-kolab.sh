#!/bin/bash

. ./settings.sh


echo ${CMD} | tee -a /root/setup-kolab.log
echo -n "Wait for MariaDB container: " | tee -a /root/setup-kolab.log
while ! mysqladmin -u root ping > /dev/null 2>&1 ; do
        echo -n '.'
        sleep 3
done | tee -a /root/setup-kolab.log
echo "OK!" | tee -a /root/setup-kolab.log

# No need to wait if we start ldap on the same host
# echo -n "Wait for DS389 container: " | tee -a /root/setup-kolab.log
# while ! ldapsearch -h ${LDAP_HOST} -D "${LDAP_ADMIN_BIND_DN}" -w "${LDAP_ADMIN_BIND_PW}" -b "" -s base > /dev/null 2>&1 ; do
#         echo -n '.'
#         sleep 3
# done | tee -a /root/setup-kolab.log
# echo "OK!" | tee -a /root/setup-kolab.log


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

# Configure roundcube and setup db
# The db setup will just fail if the db already exists,
# but no harm done
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

