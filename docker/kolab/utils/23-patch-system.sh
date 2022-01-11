#!/bin/bash

PATCHPATH=$(pwd)/patches

pushd /usr/lib/python2.7/site-packages/ || exit
patch -p1 < "$PATCHPATH/0001-Resolve-base_dn-in-kolab_user_base_dn-user_base_dn-a.patch"
patch -p1 < "$PATCHPATH/0001-Make-iTip-messages-outlook-compatible.patch"
patch -p1 < "$PATCHPATH/0002-Implement-ACT_STORE_AND_NOTIFY-policy-for-resources-.patch"
popd || exit
systemctl restart kolabd
systemctl restart wallace
