#!/bin/bash
# This is incomplete and clearly not how it's supposed to be done,
# but it will work for now.

cp -Rf /src/roundcubemail/program /usr/share/roundcubemail/program

pushd /src/roundcubemail-plugins-kolab/plugins
cp -f calendar/*.js /usr/share/roundcubemail/public_html/assets/plugins/calendar/
find calendar/ -type f \( -name "*.php" -o -name "*.inc" \) ! -name config.inc.php -exec cp -v {} /usr/share/roundcubemail/plugins/{} \;

find libcalendaring/ -type f \( -name "*.php" -o -name "*.inc" \) ! -name config.inc.php -exec cp -v {} /usr/share/roundcubemail/plugins/{} \;
cp -f libcalendaring/*.js /usr/share/roundcubemail/public_html/assets/plugins/libcalendaring/

find libkolab/ -type f \( -name "*.php" -o -name "*.inc" \) ! -name config.inc.php -exec cp -v {} /usr/share/roundcubemail/plugins/{} \;
cp -f libkolab/*.js /usr/share/roundcubemail/public_html/assets/plugins/libkolab/
popd

systemctl reload httpd
