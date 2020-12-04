FROM fedora:31

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

ENV CFLAGS="-O2 -g -pipe -Wall -Werror=format-security -Wp,-D_FORTIFY_SOURCE=2 -Wp,-D_GLIBCXX_ASSERTIONS -fstack-protector-strong -grecord-gcc-switches -specs=/usr/lib/rpm/redhat/redhat-hardened-cc1 -specs=/usr/lib/rpm/redhat/redhat-annobin-cc1 -m64 -fasynchronous-unwind-tables -fstack-clash-protection -flto"
ENV CXXFLAGS="${CFLAGS}"
ENV LDFLAGS="-Wl,-z,relro -Wl,--as-needed  -Wl,-z,now -specs=/usr/lib/rpm/redhat/redhat-hardened-ld -flto -fuse-linker-plugin"
ENV CC="gcc"
ENV CXX="g++"
ENV AR="/bin/gcc-ar"
ENV RANLIB="/bin/gcc-ranlib"
ENV NM="/bin/gcc-nm"
ENV CMAKE_CXX_ARCHIVE_CREATE="<CMAKE_AR> qcs <TARGET> <LINK_FLAGS> <OBJECTS>"
ENV OPENSSL_CONF=''

ARG CMAKEOPTS="-DCMAKE_BUILD_TYPE=Release -DCMAKE_INSTALL_PREFIX=/usr/ -DCMAKE_INSTALL_SYSCONFDIR=/etc/ -DCMAKE_MODULES_INSTALL_DIR=/usr/share/cmake/Modules/ -DBUILD_SHARED_LIBS=ON -DCMAKE_AR=${AR} -DCMAKE_CXX_ARCHIVE_FINISH=true -DCMAKE_MODULES_INSTALL_DIR=/usr/share/cmake/Modules/ -DGENERATE_TESTS=FALSE -DDISABLE_TESTS=TRUE"
ARG GST_BASE_VERSION="1.8.1-1kurento2"
ARG GST_VERSION="1.8.1"
ARG KURENTO_MEDIA_SERVER_VERSION="6.15.0"
ARG KURENTO_CMAKE_VERSION="6.15.0"
ARG KURENTO_CORE_VERSION="6.15.0"
ARG KURENTO_ELEMENTS_VERSION="6.15.0"
ARG KURENTO_FILTERS_VERSION="6.15.0"
ARG KURENTO_MODULE_CREATOR_VERSION="6.15.0"
ARG KURENTO_JSONCPP_VERSION="1.6.4"
ARG KURENTO_JSONRPC_VERSION="6.15.0"
ARG OPENCV_VERSION="3.4.10"

RUN rpm -Uvh https://mirrors.rpmfusion.org/free/fedora/rpmfusion-free-release-31.noarch.rpm && \
    rpm -Uvh https://mirrors.rpmfusion.org/nonfree/fedora/rpmfusion-nonfree-release-31.noarch.rpm && \
    dnf -y install \
        autoconf \
        automake \
        bison \
        boost-devel \
        cmake \
        coreutils \
        dnf-plugins-core \
        ffmpeg \
        ffmpeg-devel \
        flac-devel \
        flex \
        gcc-c++ \
        gettext-devel \
        git \
        glibmm24-devel \
        gsl-devel \
        gtk-doc \
        libevent-devel \
        libjpeg-devel \
        libmicrodns-devel \
        libpng-devel \
        libsigc++20-devel \
        libsoup-devel \
        libsrtp-devel \
        libtool \
        libunwind-devel \
        libuuid-devel \
        libvisual-devel \
        libvpx-devel \
        make \
        maven \
        meson \
        net-tools \
        opencl-headers \
        opencv-devel \
        # not available on ppc64le? \
        #openni-devel \
        openssl-devel \
        opus-devel \
        patch \
        redhat-rpm-config \
        spandsp-devel \
        usrsctp-devel \
        vim-enhanced \
        webrtc-audio-processing-devel \
        websocketpp-devel \
        x264-devel \
        x265-devel && \
    # meson in module older than meson in updates \
    dnf module -y disable meson && \
    dnf -y update meson && \
    rpm -e --nodeps gstreamer1 gstreamer1-plugins-base && \
    dnf -y clean all && \
    mkdir -p /src/ && \
    if [ "${GST_BASE_VERSION}" == "1.8.1-1kurento2" ]; then \
        git clone https://github.com/Kurento/gstreamer.git /src/gstreamer.git && \
            cd /src/gstreamer.git/ && \
            sed -i \
                -e 's|git://anongit.freedesktop.org/gstreamer/common.*$|https://anongit.freedesktop.org/git/gstreamer/common.git/|g' \
                .gitmodules && \
            git submodule init && \
            git submodule update && \
            #sed -r -i \
            #    -e 's/^GST_API_VERSION=.*$/GST_API_VERSION=1.0/g' \
            #    configure.ac && \
            autopoint --force && \
            autoreconf -vi && \
            ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests --disable-static --disable-static-plugins --enable-shared && \
            make -j $(nproc) VERBOSE=1 && \
            make install VERBOSE=1 ; \
    else \
        git clone -b ${GST_BASE_VERSION} https://gitlab.freedesktop.org/gstreamer/gstreamer.git /src/gstreamer.git && \
            cd /src/gstreamer.git/ && \
            if [ -f 'meson.build' ]; then \
                meson build --prefix=/usr && \
                ninja -C build install ; \
            else \
                sed -i \
                    -e 's|git://anongit.freedesktop.org/gstreamer/common.*$|https://anongit.freedesktop.org/git/gstreamer/common.git/|g' \
                    .gitmodules && \
                git submodule init && \
                git submodule update && \
                autopoint --force && \
                autoreconf -vi && \
                ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests --disable-static --disable-static-plugins --enable-shared && \
                make -j $(nproc) VERBOSE=1 && \
                make install VERBOSE=1 ; \
            fi ; \
    fi && \
    git clone https://gitlab.freedesktop.org/libnice/libnice.git /src/libnice.git && \
        cd /src/libnice.git && \
        sed -r -i \
            -e 's/-(base|check)-1\.0/-\1-1.5/g' \
            -e 's/gstreamer-1\.0/gstreamer-1.5/g' \
            meson.build \
            tests/meson.build && \
        meson build --prefix=/usr && \
        ninja -C build install && \
    git clone -b ${GST_VERSION} https://gitlab.freedesktop.org/gstreamer/gst-plugins-base.git /src/gst-plugins-base.git && \
        cd /src/gst-plugins-base.git/ && \
        if [ -f 'meson.build' ]; then \
            meson build --prefix=/usr && \
            ninja -C build install ; \
        else \
            sed -i \
                -e 's|git://anongit.freedesktop.org/gstreamer/common.*$|https://anongit.freedesktop.org/git/gstreamer/common.git/|g' \
                .gitmodules && \
            git submodule init && \
            git submodule update && \
            sed -r -i \
                -e 's/^GST_API_VERSION.*$/GST_API_VERSION=1.5/g' \
                configure.ac && \
            sed -r -i \
                -e 's/^DOMAIN.*$/DOMAIN = gst-plugins-base-1.5/g' \
                po/Makevars && \
            autopoint --force && \
            autoreconf -vi && \
            sed -r -i \
                -e 's/@GST_API_VERSION@\.1/@GST_API_VERSION@/g' \
                tools/Makefile.in && \
            ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests --disable-static --disable-static-plugins --enable-shared && \
            make -j $(nproc) VERBOSE=1 && \
            make install VERBOSE=1 ; \
        fi && \
    git clone -b ${GST_VERSION} https://gitlab.freedesktop.org/gstreamer/gst-plugins-good.git /src/gst-plugins-good.git/ && \
        cd /src/gst-plugins-good.git/ && \
        if [ -f 'meson.build' ]; then \
            meson build --prefix=/usr && \
            ninja -C build install ; \
        else \
            sed -i \
                -e 's|git://anongit.freedesktop.org/gstreamer/common.*$|https://anongit.freedesktop.org/git/gstreamer/common.git/|g' \
                .gitmodules && \
            git submodule init && \
            git submodule update && \
            sed -r -i \
                -e 's/^GST_API_VERSION.*$/GST_API_VERSION=1.5/g' \
                configure.ac && \
            sed -r -i \
                -e 's/^DOMAIN.*$/DOMAIN = gst-plugins-good-1.5/g' \
                po/Makevars && \
            autopoint --force && \
            autoreconf -vi && \
            sed -i \
                -e '/^#define .* gint64/d' \
                -e '/^#define .* guint64/d' \
                sys/v4l2/ext/types-compat.h && \
            sed -i \
                -e 's/__s64/gint64/g' \
                -e 's/__u64/guint64/g' \
                sys/v4l2/ext/videodev2.h && \
            ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests --disable-static --disable-static-plugins --enable-shared && \
            make -j $(nproc) VERBOSE=1 && \
            make install VERBOSE=1 ; \
        fi && \
    git clone https://github.com/kanarip/gst-plugins-bad.git /src/gst-plugins-bad.git && \
        cd /src/gst-plugins-bad.git/ && \
        if [ -f 'meson.build' ]; then \
            meson build --prefix=/usr && \
            ninja -C build install ; \
        else \
            sed -i \
                -e 's|git://anongit.freedesktop.org/gstreamer/common.*$|https://anongit.freedesktop.org/git/gstreamer/common.git/|g' \
                .gitmodules && \
            git submodule init && \
            git submodule update && \
            sed -r -i \
                -e 's/^GST_API_VERSION.*$/GST_API_VERSION=1.5/g' \
                -e 's/gstreamer-allocators-1.0/gstreamer-allocators-1.5/g' \
                configure.ac && \
            sed -r -i \
                -e 's/gstaudio-1.0/gstaudio-1.5/g' \
                -e 's/gstvideo-1.0/gstvideo-1.5/g' \
                gst/festival/Makefile.am \
                gst/ivtc/Makefile.am \
                gst/yadif/Makefile.am && \
            sed -r -i \
                -e 's/^DOMAIN.*$/DOMAIN = gst-plugins-bad-1.5/g' \
                po/Makevars && \
            autopoint --force && \
            autoreconf -vi && \
            #./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests --disable-dtls && \
            ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests --disable-static --disable-static-plugins --enable-shared && \
            make -j $(nproc) VERBOSE=1 && \
            make install VERBOSE=1 ; \
        fi && \
    git clone -b ${GST_VERSION} https://gitlab.freedesktop.org/gstreamer/gst-plugins-ugly.git /src/gst-plugins-ugly.git && \
        cd /src/gst-plugins-ugly.git/ && \
        if [ -f 'meson.build' ]; then \
            meson build --prefix=/usr && \
            ninja -C build install ; \
        else \
            sed -i \
                -e 's|git://anongit.freedesktop.org/gstreamer/common.*$|https://anongit.freedesktop.org/git/gstreamer/common.git/|g' \
                .gitmodules && \
            git submodule init && \
            git submodule update && \
            sed -r -i \
                -e 's/^GST_API_VERSION.*$/GST_API_VERSION=1.5/g' \
                configure.ac && \
            sed -r -i \
                -e 's/^DOMAIN.*$/DOMAIN = gst-plugins-ugly-1.5/g' \
                po/Makevars && \
            autopoint --force && \
            autoreconf -vi && \
            ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests --disable-x264 --disable-static --disable-static-plugins --enable-shared && \
            make -j $(nproc) VERBOSE=1 && \
            make install VERBOSE=1 ; \
        fi && \
    git clone https://github.com/Kurento/openwebrtc-gst-plugins.git /src/openwebrtc-gst-plugins.git && \
        cd /src/openwebrtc-gst-plugins.git/ && \
        autoreconf -vi && \
        ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-static --disable-static-plugins --enable-shared && \
        make VERBOSE=1 && \
        make install VERBOSE=1 && \
    git clone -b ${KURENTO_CMAKE_VERSION} https://github.com/Kurento/kms-cmake-utils.git /src/kms-cmake-utils.git && \
        cd /src/kms-cmake-utils.git/ && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            .. && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
    git clone -b ${KURENTO_MODULE_CREATOR_VERSION} https://github.com/Kurento/kurento-module-creator.git /src/kurento-module-creator.git && \
        cd /src/kurento-module-creator.git/ && \
        mvn clean install -DskipTests && \
        cp target/kurento-module-creator-jar-with-dependencies.jar /usr/bin/. && \
        cp scripts/kurento-module-creator /usr/bin/. && \
        cp target/classes/*.cmake /usr/share/cmake/Modules/. && \
    git clone -b ${KURENTO_JSONCPP_VERSION} https://github.com/Kurento/jsoncpp.git /src/jsoncpp.git && \
        cd /src/jsoncpp.git/ && \
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
    git clone -b ${KURENTO_JSONRPC_VERSION} https://github.com/Kurento/kms-jsonrpc.git /src/kms-jsonrpc.git && \
        cd /src/kms-jsonrpc.git/ && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            .. && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
    git clone -b ${KURENTO_CORE_VERSION} https://github.com/Kurento/kms-core.git /src/kms-core.git && \
        cd /src/kms-core.git/ && \
        mkdir build && \
        cd build && \
        if [ "${GST_BASE_VERSION}" != "1.8.1-1kurento2" ]; then \
            sed -r -i \
                -e 's/gstreamer-(.*)1.5/gstreamer-\11.0/g' \
                $(find -type f -name CMakeLists.txt) && \
            cmake \
                ${CMAKEOPTS} \
                -DCMAKE_INSTALL_GST_PLUGINS_DIR=/usr/lib64/gstreamer-1.0/ \
                .. ; \
        else \
            cmake \
                ${CMAKEOPTS} \
                -DCMAKE_INSTALL_GST_PLUGINS_DIR=/usr/lib64/gstreamer-1.5/ \
                .. ; \
        fi && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
    git clone -b ${KURENTO_ELEMENTS_VERSION} https://github.com/Kurento/kms-elements.git /src/kms-elements.git && \
        cd /src/kms-elements.git/ && \
        if [ "${GST_BASE_VERSION}" != "1.8.1-1kurento2" ]; then \
            sed -r -i \
                -e 's/gstreamer-(.*)1.5/gstreamer-\11.0/g' \
                $(find -type f -name CMakeLists.txt) ; \
        fi && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            .. && \
        sed -i \
            -e 's/-lkmsgstcommons/-lkmsgstcommons -lkmscoreinterface/g' \
            $(find . -type f -name link.txt) && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
        cp -av src/server/implementation/HttpServer/libkmshttpep.so /usr/lib64/ && \
    git clone -b ${KURENTO_FILTERS_VERSION} https://github.com/Kurento/kms-filters.git /src/kms-filters.git && \
        cd /src/kms-filters.git/ && \
        if [ "${GST_BASE_VERSION}" != "1.8.1-1kurento2" ]; then \
            sed -r -i \
                -e 's/gstreamer-(.*)1.5/gstreamer-\11.0/g' \
                $(find -type f -name CMakeLists.txt) ; \
        fi && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            .. && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
    git clone -b ${KURENTO_MEDIA_SERVER_VERSION} https://github.com/Kurento/kurento-media-server.git /src/kurento-media-server.git && \
        cd /src/kurento-media-server.git/ && \
        if [ "${GST_BASE_VERSION}" != "1.8.1-1kurento2" ]; then \
            sed -r -i \
                -e 's/gstreamer-(.*)1.5/gstreamer-\11.0/g' \
                $(find -type f -name CMakeLists.txt) ; \
        fi && \
        # work around for kurento not building on pcc64le \
        sed -i \
            -e '603,618d' \
            server/death_handler.cpp && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            .. && \
        # a linking error / oversight may occur \
        sed -i \
            -e 's/-lkmsgstcommons/-lkmsgstcommons -lkmscoreinterface/g' \
            $(find . -type f -name link.txt) && \
        make -j $(nproc) VERBOSE=1 && \
        make install VERBOSE=1 -s && \
        cp -av $(find . -type f -name 'libtransport.so*') /usr/lib64/ && \
        cp -av $(find . -type f -name 'libwebsocketTransport.so*') /usr/lib64/ && \
    strip -s \
        /usr/lib64/gstreamer-*/*.so \
        /usr/lib64/kurento/modules/*.so* \
        /usr/lib64/libkms*.so* && \
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
	        -x libsigc \
	        -x libvpx \
	        -x opencv \
	        boost-devel \
	        cmake \
	        gcc-c++ \
	        git \
	        glibmm24-devel \
	        gstreamer1-devel \
	        gstreamer1-plugins-base-devel \
	        gstreamer1-plugins-bad-free-devel \
	        libevent-devel \
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
	        /usr/include/* \
            /usr/lib64/*.la \
            /usr/lib64/gstreamer-*/*.la


COPY rootfs/ /

CMD ["/bin/kurento-media-server", "--gst-plugin-path", "/usr/lib64/gstreamer-1.0/"]
