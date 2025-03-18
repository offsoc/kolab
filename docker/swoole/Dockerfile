FROM apheleia/almalinux9

ARG PHP_VERSION=8.2
ARG SWOOLE_VERSION=v6.0.1

ENV HOME=/opt/app-root/src

RUN dnf module reset php && \
    dnf module -y enable php:${PHP_VERSION} && \
    dnf module -y enable nodejs:20 && \
    dnf -y install \
        --setopt=install_weak_deps=False \
        --setopt 'tsflags=nodocs' \
        composer \
        diffutils \
        file \
        git \
        make \
        npm \
        openssl-devel \
        openssl \
        patch \
        iputils \
        bind-utils \
        mariadb \
        procps-ng \
        php-cli \
        php-common \
        php-devel \
        php-pear \
        libsodium \
        libsodium-devel \
        php-ldap \
        php-mysqlnd \
        php-soap \
        php-opcache \
        php-pecl-apcu \
        re2c \
        wget && \
    git clone https://github.com/swoole/swoole-src.git/ /swoole-src.git/ && \
        cd /swoole-src.git/ && \
        git checkout -f ${SWOOLE_VERSION} && \
        git clean -d -f -x && \
        phpize --clean && \
        phpize && \
        ./configure \
            --enable-sockets \
            --disable-mysqlnd \
            --enable-openssl && \
        make -j4 && \
        make install && \
        cd / && \
    rm -rf /swoole-src.git/ && \
    pecl channel-update pecl.php.net && \
    pecl install -f libsodium && \
    dnf -y remove \
        diffutils \
        file \
        make \
        gcc \
        gcc-c++ \
        libsodium-devel \
        openssl-devel \
        php-devel \
        php-pear \
        re2c && \
    dnf clean all && \
    echo "extension=swoole.so" >> /etc/php.d/swoole.ini && \
    echo "extension=sodium.so" >> /etc/php.d/sodium.ini && \
    php -m 2>&1 | grep -q swoole

RUN id default || (groupadd -g 1001 default && useradd -d /opt/app-root/ -u 1001 -g 1001 default)

USER 1001

WORKDIR ${HOME}

COPY /rootfs /

EXPOSE 8000

CMD [ "/usr/local/bin/usage" ]
