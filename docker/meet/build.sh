#!/bin/bash
set -e
mkdir /src/
cd /src/
git clone --branch $GIT_REF https://git.kolab.org/source/kolab.git kolab
pushd kolab
git reset --hard $GIT_REF
popd
cp -R kolab/meet/server /src/meetsrc
rm -Rf /src/meetsrc/node_modules
cd /src/meetsrc
npm install
npm install -g nodemon
