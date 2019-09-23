#!/bin/bash

cp /etc/hosts /etc/hosts.orig
tac /etc/hosts.orig > /etc/hosts

if [ ! -d "/etc/dirsrv/slapd-kolab/" ]; then
    setup-kolab \
        --default \
        --fqdn=kolab.mgmt.com \
        --timezone=Europe/Zurich \
        --mysqlserver=new \
        --directory-manager-pwd=Welcome2KolabSystems 2>&1 | tee /root/setup-kolab.log
fi

pushd /root/utils/
./01-reset-kolab-service-password.sh
./02-reset-cyrus-admin-password.sh
./03-create-hosted-kolab-service.sh
./04-create-ou-domains.sh
./05-create-management-domain.sh
./06-create-hosted-domain.sh
./07-remove-cn-kolab-cn-config.sh
./08-remove-hosted-service-access-from-mgmt-domain.sh
./09-adjust-kolab-conf.sh
popd
