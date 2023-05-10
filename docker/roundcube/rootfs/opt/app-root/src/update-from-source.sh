#!/bin/bash
#Update from source (rather then via composer which updates to the latest commit)

for repo in roundcubemail syncroton iRony chwala autoconf freebusy
do
    if [ -d /src.orig/$directory ]; then
        rsync -av \
            --no-links \
            --exclude=vendor \
            --exclude=temp \
            --exclude=config \
            --exclude=logs \
            --exclude=.git \
            --exclude=config.inc.php \
            --exclude=composer.json \
            --exclude=composer.lock \
            /src.orig/$directory/ /opt/app-root/src/$directory
    fi
done

pushd /src.orig/roundcubemail-plugins-kolab/plugins

for plugin in $(ls -1d)
do
    if [ -d /opt/app-root/src/roundcubemail/plugins/${plugin}/ ]; then
        rsync -av \
            --exclude=vendor \
            --exclude=composer.json \
            --exclude=config.inc.php \
            $plugin/ /opt/app-root/src/roundcubemail/plugins/$plugin
    fi
done
popd


./reload.sh
