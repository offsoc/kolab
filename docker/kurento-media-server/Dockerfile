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

ARG CMAKEOPTS="-DCMAKE_BUILD_TYPE=Release -DCMAKE_INSTALL_PREFIX=/usr/ -DCMAKE_INSTALL_SYSCONFDIR=/etc/ -DCMAKE_MODULES_INSTALL_DIR=/usr/share/cmake/Modules/ -DBUILD_SHARED_LIBS=ON -DCMAKE_AR=${AR} -DCMAKE_CXX_ARCHIVE_FINISH=true -DCMAKE_MODULES_INSTALL_DIR=/usr/share/cmake/Modules/ -DGENERATE_TESTS=FALSE -DDISABLE_TESTS=TRUE"
ARG GST_VERSION="1.18.1"
ARG KURENTO_MEDIA_SERVER_VERSION="6.15.0"
ARG KURENTO_CMAKE_VERSION="6.15.0"
ARG KURENTO_CORE_VERSION="6.15.0"
ARG KURENTO_ELEMENTS_VERSION="6.15.0"
ARG KURENTO_FILTERS_VERSION="6.15.0"
ARG KURENTO_MODULE_CREATOR_VERSION="6.15.0"
ARG KURENTO_JSONCPP_VERSION="1.6.4"
ARG KURENTO_JSONRPC_VERSION="6.15.0"

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
        flac-devel \
        flex \
        gcc-c++ \
        gettext-devel \
        git \
        glibmm24-devel \
        gsl-devel \
        gtk-doc \
        libevent-devel \
        libmicrodns-devel \
        libnice-devel \
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
        opencv-devel \
        openni-devel \
        openssl-devel \
        opus-devel \
        redhat-rpm-config \
        spandsp-devel \
        vim-enhanced \
        webrtc-audio-processing-devel \
        websocketpp-devel \
        x264-devel \
        x265-devel && \
    # meson in module older than meson in updates \
    dnf module -y disable meson && \
    dnf -y update meson && \
    # opencv may not even be needed \
    #dnf -y builddep opencv && \
    mkdir -p /src/ && \
    #git clone https://github.com/Kurento/gstreamer.git /src/gstreamer.git && \
    #    cd /src/gstreamer.git/ && \
    #    git submodule init && \
    #    git submodule update && \
    #    autopoint --force && \
    #    autoreconf -vi && \
    #    ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests && \
    #    make -j $(nproc) VERBOSE=1 && \
    #    make install VERBOSE=1 && \
    git clone -b ${GST_VERSION} https://gitlab.freedesktop.org/gstreamer/gstreamer /src/gstreamer.git && \
        cd /src/gstreamer.git/ && \
        if [ -f 'meson.build' ]; then \
            meson build --prefix=/usr && \
            ninja -C build install ; \
        else \
            git submodule init && \
            git submodule update && \
            autopoint --force && \
            autoreconf -vi && \
            ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests && \
            make -j $(nproc) VERBOSE=1 && \
            make install VERBOSE=1 ; \
        fi && \
#RUN #git clone https://github.com/opencv/opencv.git /src/opencv.git/ && \
    #    cd /src/opencv.git/ && \
    #    mkdir build && \
    #    cd build && \
    #    cmake \
    #        ${CMAKE_OPTS} \
    #        -DCV_TRACE=OFF \
    #        -DWITH_IPP=OFF \
    #        -DWITH_ITT=OFF \
    #        -DWITH_QT=OFF \
    #        -DWITH_OPENGL=ON \
    #        -DOpenGL_GL_PREFERENCE=GLVND \
    #        -DWITH_GDAL=ON \
    #        -DWITH_OPENEXR=ON \
    #        -DWITH_UNICAP=ON \
    #        -DCMAKE_SKIP_RPATH=ON \
    #        -DWITH_CAROTENE=OFF \
    #        -DENABLE_PRECOMPILED_HEADERS=OFF \
    #        -DCMAKE_BUILD_TYPE=ReleaseWithDebInfo \
    #        -DBUILD_opencv_java=ON \
    #        -DOPENCV_JAR_INSTALL_PATH=/usr/lib/java \
    #        -DWITH_TBB=ON \
    #        -DWITH_GSTREAMER=ON \
    #        -DWITH_FFMPEG=ON \
    #        -DWITH_CUDA=OFF \
    #        -DWITH_OPENNI=ON \
    #        -DWITH_XINE=ON \
    #        -DBUILD_DOCS=ON \
    #        -DBUILD_EXAMPLES=ON \
    #        -DBUILD_opencv_python2=OFF \
    #        -DINSTALL_C_EXAMPLES=OFF \
    #        -DINSTALL_PYTHON_EXAMPLES=OFF \
    #        -DENABLE_PYLINT=OFF \
    #        -DBUILD_PROTOBUF=OFF \
    #        -DPROTOBUF_UPDATE_FILES=ON \
    #        -DOPENCL_INCLUDE_DIR=/usr/include/CL \
    #        -DOPENCV_SKIP_PYTHON_LOADER=ON \
    #        -DOPENCV_EXTRA_MODULES_PATH=opencv_contrib-4.5.0/modules \
    #        -DWITH_LIBV4L=ON \
    #        -DWITH_OPENMP=ON \
    #        -DOPENCV_CONFIG_INSTALL_PATH=/usr/lib64/cmake/OpenCV \
    #        -DOPENCV_GENERATE_PKGCONFIG=ON \
    #        -DOPENCV_TEST_DATA_PATH=opencv_extra-4.5.0/testdata \
    #        -DWITH_GDCM=ON \
    #        -DWITH_MFX=ON \
    #        -DWITH_CLP=ON \
    #        -DWITH_VA=ON \
    #        -DWITH_VTK=OFF \
    #        -DWITH_VULKAN=ON -DVULKAN_INCLUDE_DIRS=/usr/include/vulkan \
    #        .. && \
    #    make -j $(nproc) VERBOSE=1 && \
    #    make install VERBOSE=1 && \
    git clone -b ${GST_VERSION} https://gitlab.freedesktop.org/gstreamer/gst-plugins-base /src/gst-plugins-base.git && \
        cd /src/gst-plugins-base.git/ && \
        if [ -f 'meson.build' ]; then \
            meson build --prefix=/usr && \
            ninja -C build install ; \
        else \
            git submodule init && \
            git submodule update && \
            autopoint --force && \
            autoreconf -vi && \
            ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests && \
            make -j $(nproc) VERBOSE=1 && \
            make install VERBOSE=1 ; \
        fi && \
    git clone -b ${GST_VERSION} https://gitlab.freedesktop.org/gstreamer/gst-plugins-good /src/gst-plugins-good.git/ && \
        cd /src/gst-plugins-good.git/ && \
        if [ -f 'meson.build' ]; then \
            meson build --prefix=/usr && \
            ninja -C build install ; \
        else \
            git submodule init && \
            git submodule update && \
            autopoint --force && \
            autoreconf -vi && \
            ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests && \
            make -j $(nproc) VERBOSE=1 && \
            make install VERBOSE=1 ; \
        fi && \
    git clone -b ${GST_VERSION} https://gitlab.freedesktop.org/gstreamer/gst-plugins-bad /src/gst-plugins-bad.git && \
        cd /src/gst-plugins-bad.git/ && \
        if [ -f 'meson.build' ]; then \
            meson build --prefix=/usr && \
            ninja -C build install ; \
        else \
            git submodule init && \
            git submodule update && \
            autopoint --force && \
            autoreconf -vi && \
            ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests && \
            make -j $(nproc) VERBOSE=1 && \
            make install VERBOSE=1 ; \
        fi && \
    git clone -b ${GST_VERSION} https://gitlab.freedesktop.org/gstreamer/gst-plugins-ugly /src/gst-plugins-ugly.git && \
        cd /src/gst-plugins-ugly.git/ && \
        if [ -f 'meson.build' ]; then \
            meson build --prefix=/usr && \
            ninja -C build install ; \
        else \
            git submodule init && \
            git submodule update && \
            autopoint --force && \
            autoreconf -vi && \
            ./configure --prefix=/usr --sysconfdir=/etc --libdir=/usr/lib64 --disable-failing-tests && \
            make -j $(nproc) VERBOSE=1 && \
            make install VERBOSE=1 ; \
        fi && \
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
    git clone -b ${KURENTO_ELEMENTS_VERSION} https://github.com/Kurento/kms-elements.git /src/kms-elements.git && \
        cd /src/kms-elements.git/ && \
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
            $(find . -type f -name link.txt) && \
        make -j $(nproc) VERBOSE=1 && \
        make install -s && \
        cp -av src/server/implementation/HttpServer/libkmshttpep.so /usr/lib64/ && \
    git clone -b ${KURENTO_FILTERS_VERSION} https://github.com/Kurento/kms-filters.git /src/kms-filters.git && \
        cd /src/kms-filters.git/ && \
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
    git clone -b ${KURENTO_MEDIA_SERVER_VERSION} https://github.com/Kurento/kurento-media-server.git /src/kurento-media-server.git && \
        cd /src/kurento-media-server.git/ && \
        sed -r -i \
            -e 's/gstreamer-(.*)1.5/gstreamer-\11.0/g' \
            $(find -type f -name CMakeLists.txt) && \
        sed -i \
            -e '603,618d' \
            server/death_handler.cpp && \
        mkdir build && \
        cd build && \
        cmake \
            ${CMAKEOPTS} \
            .. && \
        sed -i \
            -e 's/-lkmsgstcommons/-lkmsgstcommons -lkmscoreinterface/g' \
            $(find . -type f -name link.txt) && \
        make -j $(nproc) VERBOSE=1 && \
        make install VERBOSE=1 -s && \
        cp -av $(find . -type f -name 'libtransport.so*') /usr/lib64/ && \
        cp -av $(find . -type f -name 'libwebsocketTransport.so*') /usr/lib64/ && \
        strip -s /usr/lib64/libkms*.so* /usr/lib64/kurento/modules/*.so*

COPY rootfs/ /

CMD ["/bin/kurento-media-server", "--gst-plugin-path", "/usr/lib64/gstreamer-1.0/"]
