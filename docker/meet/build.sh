#!/bin/bash
set -e
mkdir /src/
cd /src/

function checkout() {
    if [ ! -d "$1" ]; then
        git clone "$2" "$1"
        pushd "$1"
        git checkout "$3" 
        popd
    fi
}

checkout kolab $GIT_REMOTE $GIT_REF

cp -R kolab/meet/server /src/meetsrc
rm -Rf /src/meetsrc/node_modules
rm -Rf /src/kolab
cd /src/meetsrc
npm install
npm install -g nodemon
