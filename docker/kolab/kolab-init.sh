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
./05-replace-localhost.sh && echo "05 done"
./06-mysql-for-kolabdev.sh && echo "06 done"
./07-adjust-base-dns.sh && echo "07 done"
./08-disable-amavisd.sh && echo "08 done"
./09-enable-debugging.sh && echo "09 done"
./10-change-port-numbers.sh && echo "10 done"
./10-reset-kolab-service-password.sh && echo "10 done"
./11-reset-cyrus-admin-password.sh && echo "11 done"
./12-create-hosted-kolab-service.sh && echo "12 done"
./13-create-ou-domains.sh && echo "13 done"
./14-create-management-domain.sh && echo "14 done"
./15-create-hosted-domain.sh && echo "15 done"
./16-remove-cn-kolab-cn-config.sh && echo "16 done"
./17-remove-hosted-service-access-from-mgmt-domain.sh && echo "17 done"
./18-adjust-kolab-conf.sh && echo "18 done"
./19-turn-on-vlv-in-roundcube.sh && echo "19 done"
./20-add-alias-attribute-index.sh && echo "20 done"
./21-adjust-postfix-config.sh && echo "21 done"
./23-patch-system.sh && echo "23 done"

touch /tmp/kolab-init.done
