#!/bin/bash

sed -i -r -e "s|$config\['kolab_files_url'\] = .*$|$config['kolab_files_url'] = 'https://' \. \$_SERVER['HTTP_HOST'] . '/chwala/';|g" /etc/roundcubemail/kolab_files.inc.php

sed -i -r -e "s|$config\['kolab_invitation_calendars'\] = .*$|$config['kolab_invitation_calendars'] = true;|g" /etc/roundcubemail/calendar.inc.php

sed -i -r -e "/^.*'contextmenu',$/a 'enigma'," /etc/roundcubemail/config.inc.php

sed -i -r -e "s|$config\['enigma_passwordless'\] = .*$|$config['enigma_passwordless'] = true;|g" /etc/roundcubemail/enigma.inc.php
sed -i -r -e "s|$config\['enigma_multihost'\] = .*$|$config['enigma_multihost'] = true;|g" /etc/roundcubemail/enigma.inc.php

echo "\$config['enigma_woat'] = true;" >> /etc/roundcubemail/enigma.inc.php

