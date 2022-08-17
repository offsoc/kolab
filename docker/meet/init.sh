#!/bin/bash
set -e
cp -R /src/meet /src/meetsrc
rm -Rf /src/meetsrc/node_modules
cd /src/meetsrc
npm install
npm install -g nodemon
export DEBUG="kolabmeet-server* mediasoup*"
nodemon server.js
