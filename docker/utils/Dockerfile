FROM apheleia/almalinux9

ENV HOME=/opt/app-root/src

# Install troubleshooting utilities
RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        bind-utils \
        bzip2 \
        cmake \
        chromium \
        chromedriver \
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
        zlib-devel && \
    dnf clean all

RUN git clone https://github.com/libwbxml/libwbxml.git && \
    cd libwbxml && \
    mkdir build && \
    cd build && \
    cmake -D CMAKE_INSTALL_PREFIX=/usr .. && \
    make && \
    make install && \
    cd .. && \
    rm -rf libwbxml

RUN pip3 install distro xmltodict dnspython && \
    pip3 install \
    --global-option=build_ext \
    --global-option="-I/usr/include/libwbxml-1.0/wbxml/" \
    git+https://github.com/kanarip/python-wbxml#egg=wbxml

RUN id default || (groupadd -g 1001 default && useradd -d /opt/app-root/ -u 1001 -g 1001 default)

USER 1001

WORKDIR ${HOME}

COPY /rootfs /
