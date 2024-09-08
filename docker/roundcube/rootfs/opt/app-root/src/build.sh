#!/bin/bash
set -e
set -x

pushd /opt/app-root/src/

function checkout() {
    if [ ! -d "$1" ]; then
        git clone "$2" "$1"
        pushd "$1"
        git checkout "$3" 
        rm -rf .git
        popd
    fi
}

# Clone what we don't find
checkout roundcubemail $GIT_REMOTE_ROUNDCUBEMAIL $GIT_REF_ROUNDCUBEMAIL
checkout roundcubemail-plugins-kolab $GIT_REMOTE_ROUNDCUBEMAIL_PLUGINS $GIT_REF_ROUNDCUBEMAIL_PLUGINS
checkout syncroton $GIT_REMOTE_SYNCROTON $GIT_REF_SYNCROTON
checkout iRony $GIT_REMOTE_IRONY $GIT_REF_IRONY
checkout chwala $GIT_REMOTE_CHWALA $GIT_REF_CHWALA
checkout autoconf $GIT_REMOTE_AUTOCONF $GIT_REF_AUTOCONF
checkout freebusy $GIT_REMOTE_FREEBUSY $GIT_REF_FREEBUSY
if [[ "$GIT_REMOTE_SKIN_ELASTIC" != ""  ]]; then 
    checkout roundcubemail-skin-elastic $GIT_REMOTE_SKIN_ELASTIC $GIT_REF_SKIN_ELASTIC
fi

pushd roundcubemail
cp /opt/app-root/src/composer.json composer.json
rm -rf vendor/ composer.lock
env COMPOSER_ALLOW_SUPERUSER=1 php -dmemory_limit=-1 $(command -v composer) install
popd

./update.sh
pushd roundcubemail

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
