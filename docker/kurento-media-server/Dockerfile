FROM fedora:31

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

# From https://github.com/Kurento/kurento-media-server/blob/master/debian/control
#
# build-essential,
# cmake,
# gstreamer1.5-plugins-good,
# kms-cmake-utils (>= 6.7.0),
# kms-core-dev (>= 6.7.0),
# kms-elements-dev (>= 6.7.0),
# kms-filters-dev (>= 6.7.0),
# kurento-module-creator (>= 6.7.0),
# libboost-dev,
# libboost-filesystem-dev,
# libboost-log-dev,
# libboost-program-options-dev,
# libboost-system-dev,
# libboost-test-dev,
# libboost-thread-dev,
# libevent-dev,
# libglibmm-2.4-dev,
# libgstreamer-plugins-base1.5-dev,
# libsigc++-2.0-dev,
# libwebsocketpp-dev

ENV CFLAGS="-O2 -g -pipe -Wall -Werror=format-security -Wp,-D_FORTIFY_SOURCE=2 -Wp,-D_GLIBCXX_ASSERTIONS -fstack-protector-strong -grecord-gcc-switches -specs=/usr/lib/rpm/redhat/redhat-hardened-cc1 -specs=/usr/lib/rpm/redhat/redhat-annobin-cc1 -m64 -mtune=power9 -fasynchronous-unwind-tables -fstack-clash-protection -fcf-protection -flto"
ENV CXXFLAGS=${CFLAGS}
ENV LDFLAGS='-Wl,-z,relro -Wl,--as-needed  -Wl,-z,now -specs=/usr/lib/rpm/redhat/redhat-hardened-ld -flto -fuse-linker-plugin'
ENV CC=gcc
ENV CXX=g++
ENV AR=/bin/gcc-ar
ENV RANLIB=/bin/gcc-ranlib
ENV NM=/bin/gcc-nm
ENV CMAKE_CXX_ARCHIVE_CREATE="<CMAKE_AR> qcs <TARGET> <LINK_FLAGS> <OBJECTS>"

ARG CMAKEOPTS="-DCMAKE_BUILD_TYPE=Release -DCMAKE_INSTALL_PREFIX=/usr/ -DCMAKE_INSTALL_SYSCONFDIR=/etc/ -DCMAKE_MODULES_INSTALL_DIR=/usr/share/cmake/Modules/ -DBUILD_SHARED_LIBS=ON -DCMAKE_AR=${AR} -DCMAKE_CXX_ARCHIVE_FINISH=true -DCMAKE_MODULES_INSTALL_DIR=/usr/share/cmake/Modules/ -DGENERATE_TESTS=FALSE -DDISABLE_TESTS=TRUE"
ARG KURENTO_VERSION="6.14.0"
ARG KURENTO_JSONCPP_VERSION="1.6.4"

RUN dnf -y install \
        boost-devel \
        cmake \
        coreutils \
        #ffmpeg \
        gcc-c++ \
        git \
        glibmm24-devel \
        gstreamer1-devel \
        gstreamer1-plugins-base-devel \
        gstreamer1-plugins-bad-free-devel \
        gstreamer1-plugins-good \
        libevent-devel \
        libnice-devel \
        libsigc++20-devel \
        libsoup-devel \
        libuuid-devel \
        libvpx-devel \
        make \
        maven \
        opencv-devel \
        openssl-devel \
        redhat-rpm-config \
        vim-enhanced \
        websocketpp-devel && \
    mkdir -p /src/ && \
    git clone https://github.com/Kurento/kms-cmake-utils.git /src/kms-cmake-utils.git && \
        cd /src/kms-cmake-utils.git/ && \
        git checkout ${KURENTO_VERSION} && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            .. && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
    git clone https://github.com/Kurento/kurento-module-creator.git /src/kurento-module-creator.git && \
        cd /src/kurento-module-creator.git/ && \
        git checkout ${KURENTO_VERSION} && \
        mvn clean install -DskipTests && \
        cp target/kurento-module-creator-jar-with-dependencies.jar /usr/bin/. && \
        cp scripts/kurento-module-creator /usr/bin/. && \
        cp target/classes/*.cmake /usr/share/cmake/Modules/. && \
    git clone https://github.com/Kurento/jsoncpp.git /src/jsoncpp.git && \
        cd /src/jsoncpp.git/ && \
        git checkout ${KURENTO_JSONCPP_VERSION} && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            -DJSONCPP_WITH_CMAKE_PACKAGE=ON \
            -DLIBRARY_INSTALL_DIR=/usr/lib64/ \
            -DPACKAGE_INSTALL_DIR=/usr/share/cmake/ \
            .. && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
        mv -v /usr/lib/pkgconfig/*.pc /usr/share/pkgconfig/. && \
        mv -v /usr/share/cmake/jsoncpp/*.cmake /usr/share/cmake/Modules/. && \
        rm -rvf /usr/share/cmake/jsoncpp/ && \
        mv -v /usr/include/json/features.h /usr/include/json/json-features.h && \
        sed -i -e 's/features\.h/json-features\.h/g' /usr/include/json/*.h && \
        cp -av /usr/include/json /usr/include/kmsjsoncpp && \
    git clone https://github.com/Kurento/kms-jsonrpc.git /src/kms-jsonrpc.git && \
        cd /src/kms-jsonrpc.git/ && \
        git checkout ${KURENTO_VERSION} && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            .. && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
    git clone https://github.com/Kurento/kms-core.git /src/kms-core.git && \
        cd /src/kms-core.git/ && \
        git checkout ${KURENTO_VERSION} && \
        sed -r -i \
            -e 's/gstreamer-(.*)1.5/gstreamer-\11.0/g' \
            $(find -type f -name CMakeLists.txt) && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            -DCMAKE_INSTALL_GST_PLUGINS_DIR=/usr/lib64/gstreamer-1.0/ \
            .. && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
    git clone https://github.com/Kurento/kms-elements.git /src/kms-elements.git && \
        cd /src/kms-elements.git/ && \
        git checkout ${KURENTO_VERSION} && \
        sed -r -i \
            -e 's/gstreamer-(.*)1.5/gstreamer-\11.0/g' \
            $(find -type f -name CMakeLists.txt) && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            .. && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
    git clone https://github.com/Kurento/kms-filters.git /src/kms-filters.git && \
        cd /src/kms-filters.git/ && \
        git checkout ${KURENTO_VERSION} && \
        sed -r -i \
            -e 's/gstreamer-(.*)1.5/gstreamer-\11.0/g' \
            $(find -type f -name CMakeLists.txt) && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            .. && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
    git clone https://github.com/Kurento/kurento-media-server.git /src/kurento-media-server.git && \
        cd /src/kurento-media-server.git/ && \
        git checkout ${KURENTO_VERSION} && \
        sed -r -i \
            -e 's/gstreamer-(.*)1.5/gstreamer-\11.0/g' \
            $(find -type f -name CMakeLists.txt) && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            .. && \
        sed -i \
            -e 's/-lkmsgstcommons/-lkmsgstcommons -lkmscoreinterface/g' \
            server/CMakeFiles/kurento-media-server.dir/link.txt && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
        cp -av $(find . -type f -name 'libtransport.so*') /usr/lib64/ && \
        cp -av $(find . -type f -name 'libwebsocketTransport.so*') /usr/lib64/ && \
        strip -s /usr/lib64/libkms*.so* /usr/lib64/kurento/modules/*.so* && \
    dnf -y remove \
        -x boost-filesystem \
        -x boost-program-options \
        -x boost-log \
        -x boost-thread \
        -x file \
        -x gstreamer1-plugins-base \
        -x gstreamer1-plugins-bad-free \
        -x glibmm24 \
        -x java-1.8.0-openjdk \
        -x libnice \
        -x libsigc \
        -x libvpx \
        -x opencv \
        boost-devel \
        cmake \
        #ffmpeg \
        gcc-c++ \
        git \
        glibmm24-devel \
        gstreamer1-devel \
        gstreamer1-plugins-base-devel \
        gstreamer1-plugins-bad-free-devel \
        gstreamer1-plugins-good \
        libevent-devel \
        libnice-devel \
        libsigc++20-devel \
        libsoup-devel \
        libuuid-devel \
        libvpx-devel \
        make \
        maven \
        opencv-devel \
        openssl-devel \
        redhat-rpm-config \
        vim-enhanced \
        websocketpp-devel && \
    dnf -y clean all && \
    rm -rvf \
        /src/ \
        /root/.m2/ \
        /usr/include/*

CMD ["/bin/kurento-media-server"]
