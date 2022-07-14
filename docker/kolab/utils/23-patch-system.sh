#!/bin/bash

PATCHPATH=$(pwd)/patches

pushd /usr/share/roundcubemail/ || exit
patch -p1 -l < "$PATCHPATH/0002-WOAT-support.patch"
patch -p1 -l < "$PATCHPATH/0003-PROXY-protocol-support.patch"
popd || exit
systemctl restart httpd
