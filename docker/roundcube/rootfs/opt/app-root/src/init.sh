#!/bin/bash

echo "Starting"
set -e
set -x

mkdir -p /data/pgp-home
chmod 777 /data/pgp-home

pushd /opt/app-root/src/
pushd roundcubemail

## Copy our configs over the default ones
cp /opt/app-root/src/roundcubemail-config-templates/* config/

# Initialize the db
cat > /tmp/kolab-setup-my.cnf << EOF
[client]
host=${DB_HOST}
user=root
password=${DB_ROOT_PASSWORD}
EOF

mysql --defaults-file=/tmp/kolab-setup-my.cnf <<EOF
CREATE DATABASE IF NOT EXISTS $DB_RC_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS $DB_RC_USERNAME@'%' IDENTIFIED BY '$DB_RC_PASSWORD';
ALTER USER $DB_RC_USERNAME@'%' IDENTIFIED BY '$DB_RC_PASSWORD';
GRANT ALL PRIVILEGES ON $DB_RC_DATABASE.* TO $DB_RC_USERNAME@'%';
FLUSH PRIVILEGES;
EOF

# Run roundcube and plugin database initializations
echo "Initializing tables..."
bin/initdb.sh --dir SQL/ || :

for plugin in $(find plugins -mindepth 1 -maxdepth 1 -type d | sort); do
    if [ ! -z "$(find ${plugin} -type d -name SQL)" ]; then
        for dir in $(find plugins/$(basename ${plugin})/ -type d -name SQL); do
            # Skip plugins with multiple drivers and no kolab driver
            if [ ! -z "$(echo $dir | grep driver)" ]; then
                if [ -z "$(echo $dir | grep kolab)" ]; then
                    continue
                fi
            fi

            bin/initdb.sh \
                --dir $dir \
                --package $(basename ${plugin}) \
                >/dev/null 2>&1 || :
        done
    fi
done

popd

roundcubemail/bin/initdb.sh --dir syncroton/docs/SQL/ || :
roundcubemail/bin/initdb.sh --dir chwala/doc/SQL/ || :

echo "Updating tables..."
roundcubemail/bin/updatedb.sh --dir syncroton/docs/SQL/ --package syncroton || :
roundcubemail/bin/updatedb.sh --dir roundcubemail/SQL/ --package roundcube || :
roundcubemail/bin/updatedb.sh --dir roundcubemail/plugins/libkolab/SQL/ --package libkolab || :
roundcubemail/bin/updatedb.sh --dir roundcubemail/plugins/kolab-calendar/SQL/ --package calendar-kolab || :

echo ""
echo "Done, starting httpd..."

if [ "$1" == "testsuite" ]; then
    ./update-from-source.sh || :

    sed -i "s/?>/\$config['activesync_test_username'] = 'john@kolab.org';\n?>/" roundcubemail/config/config.inc.php
    sed -i "s/?>/\$config['activesync_test_password'] = 'simple123';\n?>/" roundcubemail/config/config.inc.php
    sed -i -r -e "s/config\['activesync_init_subscriptions'\] =.*$/config['activesync_init_subscriptions'] = 0;/g" roundcubemail/config/kolab_syncroton.inc.php
    sed -i -r -e "s/config\['activesync_multifolder_blacklist_event'\] =.*$/config['activesync_multifolder_blacklist_event'] = array('windowsoutlook');/g" roundcubemail/config/kolab_syncroton.inc.php
    sed -i -r -e "s/config\['activesync_multifolder_blacklist_task'\] =.*$/config['activesync_multifolder_blacklist_task'] = array('windowsoutlook');/g" roundcubemail/config/kolab_syncroton.inc.php
    sed -i -r -e "s/config\['activesync_multifolder_blacklist_contact'\] =.*$/config['activesync_multifolder_blacklist_contact'] = array('windowsoutlook');/g" roundcubemail/config/kolab_syncroton.inc.php

    pushd syncroton
    php -S localhost:8000 &
    pushd tests

    php \
        -dmemory_limit=-1 \
        ../vendor/bin/phpunit \
        --verbose \
        --testsuite Sync
elif [ "$1" == "phpstan" ]; then
    ./update-from-source.sh || :
    php -dmemory_limit=-1 vendor/bin/phpstan analyse
elif [ "$1" == "quicktest" ]; then
    pushd syncroton/tests
    php \
        -dmemory_limit=-1 \
        ../vendor/bin/phpunit \
        --verbose \
        --testsuite Unit
elif [ "$1" == "shell" ]; then
    exec /bin/bash
else
    /usr/sbin/php-fpm
    exec httpd -DFOREGROUND
fi
