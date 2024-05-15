FROM apheleia/almalinux9

ENV HOME=/opt/app-root/src

# Install troubleshooting utilities
# FIXME not available under ppc64le
        # chromium \
        # chromedriver \
RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        bind-utils \
        bzip2 \
        cmake \
        cyrus-imapd \
        cyrus-sasl-plain \
        expat-devel \
        gcc \
        git \
        libjpeg-turbo-devel \
        lsof \
        net-tools \
        nmap-ncat \
        openldap-clients \
        openssh-server \
        php \
        psmisc \
        iputils \
        mariadb \
        procps-ng \
        python3 \
        python3-devel \
        python3-pip \
        python3-pytz \
        strace \
        telnet \
        traceroute \
        vim-enhanced \
        wget \
        jq \
        ruby \
        zlib-devel && \
    dnf clean all

RUN git clone https://github.com/libwbxml/libwbxml.git && \
    cd libwbxml && \
    mkdir build && \
    cd build && \
    cmake -D CMAKE_INSTALL_PREFIX=/usr .. && \
    make && \
    make install && \
    mv /usr/lib/libwbxml2.so* /usr/lib64/ && \
    cd .. && \
    rm -rf libwbxml

RUN pip3 install distro xmltodict dnspython dkimpy && \
    pip3 install \
    --global-option=build_ext \
    --global-option="-I/usr/include/libwbxml-1.0/wbxml/" \
    git+https://github.com/kanarip/python-wbxml#egg=wbxml

# imapcli deps
RUN gem install thor

RUN id default || (groupadd -g 1001 default && useradd -d /opt/app-root/src/ -u 1001 -g 1001 default)

COPY /rootfs /

RUN chgrp -R 0 /opt/app-root/src && \
    chmod -R g=u /opt/app-root/src && \
    chown -R 1001:0 /opt/app-root/src

USER 1001

WORKDIR ${HOME}
