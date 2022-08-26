#!/bin/bash

sed -i -r -e "s|$config\['kolab_files_url'\] = .*$|$config['kolab_files_url'] = 'https://' \. \$_SERVER['HTTP_HOST'] . '/chwala/';|g" /etc/roundcubemail/kolab_files.inc.php

sed -i -r -e "s|$config\['kolab_invitation_calendars'\] = .*$|$config['kolab_invitation_calendars'] = true;|g" /etc/roundcubemail/calendar.inc.php

sed -i -r -e "/^.*'contextmenu',$/a 'enigma'," /etc/roundcubemail/config.inc.php

sed -i -r -e "s|$config\['enigma_passwordless'\] = .*$|$config['enigma_passwordless'] = true;|g" /etc/roundcubemail/enigma.inc.php
sed -i -r -e "s|$config\['enigma_multihost'\] = .*$|$config['enigma_multihost'] = true;|g" /etc/roundcubemail/enigma.inc.php

echo "\$config['enigma_woat'] = true;" >> /etc/roundcubemail/enigma.inc.php

# Run it over nginx for 2fa. We need to use startls because otherwise the proxy protocol doesn't work.
sed -i -r -e "s|$config\['default_host'\] = .*$|$config['default_host'] = 'tls://127.0.0.1';|g" /etc/roundcubemail/config.inc.php
sed -i -r -e "s|$config\['default_port'\] = .*$|$config['default_port'] = 144;|g" /etc/roundcubemail/config.inc.php

# So we can just append
sed -i "s/?>//g" /etc/roundcubemail/config.inc.php

# Enable the PROXY protocol
cat << EOF >> /etc/roundcubemail/config.inc.php
    \$config['imap_conn_options'] = Array(
            'ssl' => Array(
                    'verify_peer_name' => false,
                    'verify_peer' => false,
                    'allow_self_signed' => true
                ),
            'proxy_protocol' => 2
        );
    \$config['proxy_whitelist'] = array('127.0.0.1');
EOF

echo "?>" >> /etc/roundcubemail/config.inc.php


# Send dns queries over powerdns
echo "server=/_woat.kolab.org/127.0.0.1#9953" >> /etc/dnsmasq.conf
echo "port=5353" >> /etc/dnsmasq.conf
systemctl start dnsmasq
rm -f /etc/resolv.conf
echo "nameserver 127.0.0.1:5353" > /etc/resolv.conf
