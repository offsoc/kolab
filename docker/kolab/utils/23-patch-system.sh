#!/bin/bash

PATCHPATH=$(pwd)/patches

# Example for applying a pykolab patch
#pushd /usr/lib/python2.7/site-packages/ || exit
#patch -p1 < "$PATCHPATH/0001-Resolve-base_dn-in-kolab_user_base_dn-user_base_dn-a.patch"
#popd || exit
#systemctl restart kolabd
#systemctl restart wallace
