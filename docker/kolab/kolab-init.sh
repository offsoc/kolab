#!/bin/bash

if [ -d "/etc/dirsrv/slapd-kolab/" ]; then
    exit 0
fi

pushd /root/utils/

./01-reverse-etc-hosts.sh
./02-write-my.cnf.sh
./03-setup-kolab.sh
./04-reset-mysql-kolab-password.sh
./05-replace-localhost.sh
./06-mysql-for-kolabdev.sh
./07-adjust-base-dns.sh
./08-disable-amavisd.sh
./09-enable-debugging.sh
./10-reset-kolab-service-password.sh
./11-reset-cyrus-admin-password.sh
./12-create-hosted-kolab-service.sh
./13-create-ou-domains.sh
./14-create-management-domain.sh
./15-create-hosted-domain.sh
./16-remove-cn-kolab-cn-config.sh
./17-remove-hosted-service-access-from-mgmt-domain.sh
./18-adjust-kolab-conf.sh
./19-turn-on-vlv-in-roundcube.sh
./20-add-alias-attribute-index.sh
./21-adjust-postfix-config.sh

touch /tmp/kolab-init.done
