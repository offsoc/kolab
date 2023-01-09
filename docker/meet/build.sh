#!/bin/bash
set -e
mkdir /src/
cd /src/
git clone https://git.kolab.org/source/kolab.git kolab
cp -R kolab/meet/server /src/meetsrc
rm -Rf /src/meetsrc/node_modules
cd /src/meetsrc
npm install
npm install -g nodemon
