#!/bin/bash
set -e
cp -R /src/meet /src/meetsrc
cd /src/meetsrc
npm install
redis-server&
npm start
