#!/bin/bash

mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
    -e "UPDATE mysql.db SET Host = '127.0.0.1' WHERE Host = 'localhost';"

mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
    -e "UPDATE mysql.user SET Host = '127.0.0.1' WHERE Host = 'localhost';"

mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems \
    -e "FLUSH PRIVILEGES;"

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

systemctl restart cyrus-imapd postfix
