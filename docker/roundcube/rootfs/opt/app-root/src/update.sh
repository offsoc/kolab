#!/bin/bash
set -e
set -x

# Look for local repositories
for repo in roundcubemail roundcubemail-plugins-kolab roundcubemail-skin-elastic syncroton iRony chwala autoconf freebusy
do
    if [ -d /src.orig/$repo ]; then
        rsync -av \
            --exclude=vendor \
            --exclude=temp \
            --exclude=logs \
            --exclude=composer.lock \
            /src.orig/$repo/ /opt/app-root/src/$repo
    fi
done

pushd /opt/app-root/src/

LESSC=/usr/local/bin/lessc
SKINS=(kolab plesk)

pushd roundcubemail
cp /opt/app-root/src/composer.json composer.json

php -dmemory_limit=-1 $(command -v composer) update

bin/install-jsdeps.sh

# May require an "npm install less" and "npm install less-plugin-clean-css"
pushd skins/elastic
$LESSC -x styles/styles.less > styles/styles.css
$LESSC -x styles/print.less > styles/print.css
$LESSC -x styles/embed.less > styles/embed.css
popd
$LESSC --clean-css="--s1 --advanced" --rewrite-urls=all plugins/libkolab/skins/elastic/libkolab.less > plugins/libkolab/skins/elastic/libkolab.min.css

bin/updatecss.sh --dir skins/elastic
popd

# Install skins
for skin in "${SKINS[@]}"; do
    if [ -d "roundcubemail-skin-elastic/$skin" ]; then
        cp -r "roundcubemail-skin-elastic/$skin" roundcubemail/skins/
    else
        echo "Skin $skin is not available"
    fi
done

pushd roundcubemail

for skin in $(ls -1d skins/* | grep -vE '(classic|elastic|larry)'); do
    skin=$(basename $skin)

    # Copy elastic skin over $skin (but don't overwrite what already existis)
    find \
        ./skins/elastic/ \
        ./plugins/libkolab/skins/elastic/ \
        -type f | sort | while read file; do
        target_dir=$(dirname ${file} | sed -e 's|%{datadir}|.|g' -e 's|./public_html/assets/|./|g' -e 's|./public_html/assets/plugins/libkolab/|./|g' -e "s/elastic/$skin/g")
        file_name=$(basename ${file})
        echo "Target: $target_dir, file $file_name"
        if [ ! -d ${target_dir} ]; then
            mkdir -p ${target_dir}
        fi
        if [ ! -f "${target_dir}/${file_name}" ]; then
            cp -av "${file}" "${target_dir}"
        fi
    done

    # Replace elastic references, but don't change the depends value in meta.json
    sed -i -e "s/\"elastic\"/\"$skin\"/g" \
        $(find skins/$skin/ plugins/libkolab/skins/$skin/ -type f -not -name "meta.json")

    pushd skins/$skin
    $LESSC -x styles/styles.less > styles/styles.css
    $LESSC -x styles/print.less > styles/print.css
    $LESSC -x styles/embed.less > styles/embed.css
    popd
    $LESSC --clean-css="--s1 --advanced" --rewrite-urls=all plugins/libkolab/skins/$skin/libkolab.less > plugins/libkolab/skins/$skin/libkolab.min.css

    # Compile and compress the CSS
    #for file in `find . -type f -name "styles.less" -o -name "print.less" -o -name "embed.less" -o -name "libkolab.less"`; do
    #    %{_bindir}/lessc --relative-urls ${file} > $(dirname ${file})/$(basename ${file} .less).css
    #
    #    sed -i \
    #        -e "s|../../../skins/plesk/images/contactpic.png|../../../../skins/plesk/images/contactpic.png|" \
    #        -e "s|../../../skins/plesk/images/watermark.jpg|../../../../skins/plesk/images/watermark.jpg|" \
    #        $(dirname ${file})/$(basename ${file} .less).css
    #
    #    cat $(dirname ${file})/$(basename ${file} .less).css
    #done

    bin/updatecss.sh --dir "skins/$skin"
done

## Configs

# Install plugin configs
for plugin in $(find plugins/ -mindepth 1 -maxdepth 1 -type d -exec basename {} \; | sort); do
    if [ -f "plugins/${plugin}/config.inc.php.dist" ]; then
        pushd plugins/${plugin}
        mv config.inc.php.dist ../../config/${plugin}.inc.php
        rm -f config.inc.php
        ln -s ../../config/${plugin}.inc.php config.inc.php
        popd
    fi
done

# Copy our configs over the default ones
cp /etc/roundcubemail/* config/

DES_KEY=$(openssl rand -base64 24);
sed -i -r -e "s|\$config\['des_key'\] = .*$|\$config['des_key'] = \"$DES_KEY\";|g" config/config.inc.php


# Update plugins on update

pushd /opt/app-root/src/roundcubemail-plugins-kolab/plugins
for plugin in $(ls -1d)
do
    if [ -d plugins/${plugin}/ ]; then
        rsync -av \
            --exclude=vendor \
            --exclude=composer.json \
            --exclude=config.inc.php \
            $plugin/ /opt/app-root/src/roundcubemail/plugins/$plugin
    fi
done
popd


##Fix permissions
chmod 777 -R logs
chmod 777 -R temp

popd

# Maybe redo this in case of updates
# Install chwala
pushd chwala
rm -f lib/ext/Roundcube lib/drivers/kolab/plugins vendor
mkdir -p lib/ext
ln -s ../../../roundcubemail/program/lib/Roundcube lib/ext/Roundcube
ln -s ../../../../roundcubemail/plugins lib/drivers/kolab/plugins
ln -s ../roundcubemail/vendor vendor
rm -R config
ln -s ../roundcubemail/config config
chmod 777 -R cache
chmod 777 -R logs
popd


# Install iRony
pushd iRony
rm -f lib/FileAPI lib/Roundcube lib/plugins vendor
ln -s ../../chwala/lib lib/FileAPI
ln -s ../../roundcubemail/program/lib/Roundcube lib/Roundcube
ln -s ../../roundcubemail/plugins lib/plugins
ln -s ../roundcubemail/vendor vendor
rm -R config
ln -s ../roundcubemail/config config
mkdir -p logs
chmod 777 -R logs
mkdir -p temp
chmod 777 -R temp
popd


# Install syncroton
pushd syncroton
rm -f lib/ext/Roundcube lib/plugins vendor
mkdir -p lib/ext
ln -s ../../../roundcubemail/program/lib/Roundcube lib/ext/Roundcube
ln -s ../../roundcubemail/plugins lib/plugins
ln -s ../roundcubemail/vendor vendor
rm -R config
ln -s ../roundcubemail/config config
chmod 777 -R logs
popd

# Install autoconf
pushd autoconf
rm -f vendor
ln -s ../roundcubemail/vendor vendor
chmod 777 -R logs
popd

# Install freebusy
pushd freebusy
rm -f vendor
ln -s ../roundcubemail/vendor vendor
mkdir -p logs
chmod 777 -R logs
popd

roundcubemail/bin/updatedb.sh --dir syncroton/docs/SQL/ --package syncroton
roundcubemail/bin/updatedb.sh --dir roundcubemail/SQL/ --package roundcube
roundcubemail/bin/updatedb.sh --dir roundcubemail/plugins/libkolab/SQL/ --package libkolab
roundcubemail/bin/updatedb.sh --dir roundcubemail/plugins/kolab-calendar/SQL/ --package calendar-kolab
