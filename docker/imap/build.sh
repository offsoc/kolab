#!/bin/bash

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
./configure CFLAGS="-W -Wno-unused-parameter -g -O0 -Wall -Wextra -Werror -fPIC" --enable-murder --enable-http --enable-calalarmd --enable-autocreate --enable-idled --with-openssl=yes --enable-replication --prefix=/usr
make -j6
make install
popd
rm -rf cyrus-imapd

