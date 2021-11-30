#!/bin/bash

pushd /src/syncroton/
cp -f lib/*.php /usr/share/kolab-syncroton/lib/
cp -Rf lib/ext/Syncroton /usr/share/kolab-syncroton/lib/ext/Syncroton
popd

systemctl reload httpd
