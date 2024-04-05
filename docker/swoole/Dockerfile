FROM apheleia/almalinux9

ARG SWOOLE_VERSION=v5.0.2
ENV HOME=/opt/app-root/src


RUN dnf module reset php && \
    dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm && \
    dnf module -y enable php:remi-8.1 && \
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
        php-ldap \
        php-mysqlnd \
        php-opcache \
        php-pecl-apcu \
        php-sodium \
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
    dnf -y remove \
        diffutils \
        file \
        make \
        openssl-devel \
        php-devel \
        re2c && \
    dnf clean all && \
    echo "extension=swoole.so" >> /etc/php.d/swoole.ini && \
    php -m 2>&1 | grep -q swoole

RUN id default || (groupadd -g 1001 default && useradd -d /opt/app-root/ -u 1001 -g 1001 default)

USER 1001

WORKDIR ${HOME}

COPY /rootfs /

EXPOSE 8000

CMD [ "/usr/local/bin/usage" ]
