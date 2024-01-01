#!/bin/bash
set -e
set -x

pushd /opt/app-root/src/

# Clone what we don't find (roundcubemail-skin-elastic is not publicly available, so can't be included this way)
if [ ! -d roundcubemail ]; then
    git clone --branch $GIT_REF_ROUNDCUBEMAIL $GIT_REMOTE_ROUNDCUBEMAIL roundcubemail
fi
if [ ! -d roundcubemail-plugins-kolab ]; then
    git clone --branch $GIT_REF_ROUNDCUBEMAIL_PLUGINS $GIT_REMOTE_ROUNDCUBEMAIL_PLUGINS roundcubemail-plugins-kolab
fi
if [ ! -d syncroton ]; then
    git clone --branch $GIT_REF_SYNCROTON $GIT_REMOTE_SYNCROTON syncroton
fi
if [ ! -d iRony ]; then
    git clone --branch $GIT_REF_IRONY $GIT_REMOTE_IRONY iRony
fi
if [ ! -d chwala ]; then
    git clone --branch $GIT_REF_CHWALA $GIT_REMOTE_CHWALA chwala
fi
if [ ! -d autoconf ]; then
    git clone --branch $GIT_REF_AUTOCONF $GIT_REMOTE_AUTOCONF autoconf
fi
if [ ! -d freebusy ]; then
    git clone --branch $GIT_REF_FREEBUSY $GIT_REMOTE_FREEBUSY freebusy
fi


pushd roundcubemail
cp /opt/app-root/src/composer.json composer.json
rm -rf vendor/ composer.lock
php -dmemory_limit=-1 $(command -v composer) install

cd /opt/app-root/src/
./update.sh
cd /opt/app-root/src/roundcubemail

# Adjust the configs

sed -i -r \
    -e "s/'vlv'(\s+)=> false,/'vlv'\1=> true,/g" \
    -e "s/'vlv_search'(\s+)=> false,/'vlv_search'\1=> true,/g" \
    -e "s/inetOrgPerson/inetorgperson/g" \
    -e "s/kolabInetOrgPerson/inetorgperson/g" \
    config/*.inc.php

sed -i -r -e "s|\$config\['managesieve_host'\] = .*$|\$config['managesieve_host'] = 'kolab';|g" config/managesieve.inc.php

popd

# Set the php timezone
sed -i -r -e 's|^(;*)date\.timezone.*$|date.timezone = Europe/Zurich|g' /etc/php.ini
# Allow environment variables from fpm
sed -i -e "s/;clear_env/clear_env/" /etc/php-fpm.d/www.conf
