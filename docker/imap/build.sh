#!/bin/bash

set -e

function checkout() {
    if [ ! -d "$1" ]; then
        git clone "$2" "$1"
        pushd "$1"
        git checkout "$3" 
        popd
    fi
}

checkout cyrus-imapd $GIT_REMOTE $GIT_REF
pushd cyrus-imapd
autoreconf -i

#-Wno-deprecated to work around the following:
#imap/http_h2.c:767:13: error: ‘MD5_Final’ is deprecated: Since OpenSSL 3.0 [-Werror=deprecated-declarations]
# imap/ctl_mboxlist.c:997:21: error: ‘free’ called on pointer ‘entry’ with nonzero offset 2052 [-Werror=free-nonheap-object]
./configure CFLAGS="-W -Wno-unused-parameter -g -O0 -Wall -Wextra -Werror -Wno-error=deprecated-declarations  -Wno-error=free-nonheap-object -fPIC" --enable-murder --enable-http --enable-calalarmd --enable-autocreate --enable-idled --with-openssl=yes --enable-replication --prefix=/usr


make -j6
make install
popd
rm -rf cyrus-imapd

