#!/bin/bash
set -e
rm -R /src/meetsrc/lib /src/meetsrc/config /src/meetsrc/test
cp -R /src/meet/lib /src/meetsrc/lib
cp -R /src/meet/config /src/meetsrc/config
cp -R /src/meet/test /src/meetsrc/test
cp -R /src/meet/*.js /src/meetsrc/
cd /src/meetsrc
npm install
npm install -g nodemon
export DEBUG="kolabmeet-server* mediasoup*"
exec nodemon server.js
