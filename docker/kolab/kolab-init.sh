#!/bin/bash

if [ -d "/etc/dirsrv/slapd-kolab/" ]; then
    exit 0
fi

cp -av /bin/true /usr/sbin/ds_systemd_ask_password_acl

pushd /root/utils/

./01-reverse-etc-hosts.sh && echo "01 done"
./02-write-my.cnf.sh && echo "02 done"
./03-setup-kolab.sh && echo "03 done"
./04-reset-mysql-kolab-password.sh && echo "04 done"
./05-adjust-configs.sh && echo "05 done"
./10-reset-kolab-service-password.sh && echo "10 done"
./11-reset-cyrus-admin-password.sh && echo "11 done"
./13-setup-ldap.sh && echo "13 done"
./23-patch-system.sh && echo "23 done"

touch /tmp/kolab-init.done
