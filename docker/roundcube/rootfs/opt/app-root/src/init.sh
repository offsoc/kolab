#!/bin/bash
set -e
set -x

pushd /opt/app-root/src/

# FIXME doesn't work in rootless
# sed -i -r -e "s|service_bind_pw = .*$|service_bind_pw = $LDAP_SERVICE_BIND_PW|g" /etc/kolab/kolab.conf

pushd roundcubemail

## Copy our configs over the default ones
cp /etc/roundcubemail/* config/

DES_KEY=$(openssl rand -base64 24);
sed -i -r -e "s|\$config\['des_key'\] = .*$|\$config['des_key'] = \"$DES_KEY\";|g" config/config.inc.php

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

# FIXME should we be runnin updates?
# bin/updatedb.sh --dir SQL/ --package roundcube
# bin/updatedb.sh --dir plugins/libkolab/SQL/ --package libkolab
# bin/updatedb.sh --dir plugins/calendar/SQL/ --package calendar

popd

roundcubemail/bin/initdb.sh --dir syncroton/docs/SQL/ || :
roundcubemail/bin/initdb.sh --dir chwala/doc/SQL/ || :

echo ""
echo "Done, starting httpd..."

/usr/sbin/php-fpm
exec httpd -DFOREGROUND
