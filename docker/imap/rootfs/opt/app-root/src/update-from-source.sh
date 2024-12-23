#!/bin/bash
#Update from source (rather than via composer which updates to the latest commit)

rsync -av \
    --no-links \
    --exclude=.git \
    --exclude='*.o' \
    --exclude='*.Plo' \
    --exclude='*.lo' \
    /src.orig/cyrus-imapd/ /opt/app-root/src/cyrus-imapd

pushd /opt/app-root/src/cyrus-imapd
autoreconf -i
./configure CFLAGS="-W -Wno-unused-parameter -Wno-error=deprecated-declarations -g -O0 -Wall -Wextra -Werror -fPIC" --enable-murder --enable-http --enable-calalarmd --enable-autocreate --enable-idled --with-openssl=yes --enable-replication --prefix=/usr
make -j6
make install
popd

./reload.sh
