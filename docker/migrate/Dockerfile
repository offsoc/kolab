FROM fedora:33

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@apheleia-it.ch>

ENV HOME=/opt/app-root/src

RUN dnf -y install \
        composer \
        file \
        git \
        make \
        npm \
        openssl-devel \
        php-cli \
        php-common \
        php-ldap \
        php-opcache \
        php-pecl-apcu \
        php-mysqlnd \
        wget \
        sudo \
        libgsf \
        gettext \
        libtool \
        gcc-c++ \
        shtool \
        automake \
        gd-devel zlib-devel boost-devel libgsf-devel gettext-devel \
        python3 python3-devel boost-python3 boost-python3-devel


RUN id default || (groupadd -g 1001 default && useradd -d /opt/app-root/ -u 1001 -g 1001 default)
RUN usermod -aG wheel default
RUN echo '%wheel ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers

RUN mkdir ${HOME} && chown 1001:1001 ${HOME}

USER 1001

WORKDIR ${HOME}

COPY /rootfs /
