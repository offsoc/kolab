#!/bin/bash
set -e
cp -R /src/meet /src/meetsrc
ln -s /root/node_modules /src/meetsrc/node_modules
cd /src/meetsrc
npm install
npm install -g nodemon
export DEBUG="*"
nodemon server.js
