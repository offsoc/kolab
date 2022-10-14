#!/bin/bash
set -e
rm -rf /src/kolab
sudo cp -a /src/kolab.orig /src/kolab
sudo chmod 777 -R /src/kolab
cd /src/kolab/src

sudo rm -rf vendor/ composer.lock
# Not required for linting and requires db, so skip
sed -i '/package:discover/c\""' composer.json
sed -i '/vendor:publish/c\""' composer.json
php -dmemory_limit=-1 $(command -v composer) install
sudo rm -rf node_modules
mkdir node_modules
npm install

php -dmemory_limit=-1 \
    vendor/bin/phpcs \
    -s

php -dmemory_limit=-1 \
    vendor/bin/phpstan \
    analyse

npm run lint
