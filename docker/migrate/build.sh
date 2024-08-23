#!/bin/bash

set -x
set -e

# Build libpst
git clone -b stable/kolab-0.6.76 https://git.kolab.org/source/libpst.git
cd libpst
autoreconf -vif
# ./configure --enable-libpst-shared --with-boost-python=boost_python39
./configure --enable-python=no --prefix=/usr
# Override the configure result
echo "#define HAVE_ICONV 1"  >> config.h
make -j5
make install
