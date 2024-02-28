#!/bin/bash
set -e
set -x

echo -e "Building with the following ulimit: limit: $(ulimit -n)\n"
echo -e "If you run into EMFILE errors, this is the reason"

mkdir /src
cd /src

function checkout() {
    if [ ! -d "$1" ]; then
        git clone "$2" "$1"
        pushd "$1"
        git checkout "$3" 
        popd
    fi
}

checkout kolab $GIT_REMOTE $GIT_REF

pushd kolab
#TODO support injecting a custom overlay into the build process here
bin/configure.sh $CONFIG
# In the docker-compose case we copy the .env file during the init phase, otherwise we use the environment for configuration.
rm src/.env
popd

rm -rf /opt/app-root/src
cp -a kolab/src /opt/app-root/src
cd /opt/app-root/src/

mkdir -p storage/framework/{sessions,views,cache}
mkdir -p database/seeds

php -dmemory_limit=-1 $(command -v composer) install
npm -g install npm
/usr/local/bin/npm install
./artisan storage:link
./artisan clear-compiled
./artisan horizon:install
if [ ! -f 'resources/countries.php' ]; then
    ./artisan data:countries
fi

/usr/local/bin/npm run $RELEASE_MODE
