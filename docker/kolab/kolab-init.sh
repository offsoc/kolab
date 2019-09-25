#!/bin/bash

cp /etc/hosts /etc/hosts.orig
tac /etc/hosts.orig > /etc/hosts

if [ ! -d "/etc/dirsrv/slapd-kolab/" ]; then
    cat > /root/.my.cnf << EOF
[client]
host=127.0.0.1
user=root
password=Welcome2KolabSystems
EOF

    setup-kolab \
        --default \
        --fqdn=kolab.mgmt.com \
        --timezone=Europe/Zurich \
        --mysqlhost=127.0.0.1 \
        --mysqlserver=existing \
        --mysqlrootpw=Welcome2KolabSystems \
        --directory-manager-pwd=Welcome2KolabSystems 2>&1 | tee /root/setup-kolab.log

    sqlpw=$(grep ^sql_uri /etc/kolab/kolab.conf | awk -F':' '{print $3}' | awk -F'@' '{print $1}')

    mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
        -e "SET PASSWORD FOR user 'kolab'@'localhost' = PASSWORD('${sqlpw}');"

    mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
        -e "UPDATE mysql.user SET Host = '127.0.0.1' WHERE Host = 'localhost';"

    mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
        -e "UPDATE mysql.db SET Host = '127.0.0.1' WHERE Host = 'localhost';"

    mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
        -e "GRANT ALL PRIVILEGES ON kolabdev.* TO 'kolabdev'@'127.0.0.1' IDENTIFIED BY 'kolab';"

    mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
        -e "FLUSH PRIVILEGES;"

    mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
        -e "CREATE DATABASE kolabdev;"

    sed -i -e 's/localhost/127.0.0.1/g' \
        /etc/imapd.conf \
        /etc/iRony/dav.inc.php \
        /etc/kolab/kolab.conf \
        /etc/kolab-freebusy/config.ini \
        /etc/postfix/ldap/*.cf \
        /etc/roundcubemail/password.inc.php \
        /etc/roundcubemail/kolab_auth.inc.php \
        /etc/roundcubemail/config.inc.php \
        /etc/roundcubemail/calendar.inc.php

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
fi

touch /tmp/kolab-init.done
