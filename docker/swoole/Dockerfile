FROM fedora:34

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@apheleia-it.ch>

LABEL io.k8s.description="Platform for serving PHP applications under Swoole" \
      io.k8s.display-name="Swoole 4.6.x" \
      io.openshift.expose-services="8000:http" \
      io.openshift.tags="builder,php,swoole"

ENV SWOOLE_VERSION=4.6.x \
    HOME=/opt/app-root/src

RUN dnf -y install \
        composer \
        diffutils \
        file \
        git \
        make \
        npm \
        openssl-devel \
        patch \
        php-cli \
        php-common \
        php-devel \
        php-ldap \
        php-opcache \
        php-pecl-apcu \
        php-mysqlnd \
        re2c \
        wget && \
    git clone -b v${SWOOLE_VERSION} https://github.com/swoole/swoole-src.git/ /swoole-src.git/ && \
        cd /swoole-src.git/ && \
        git clean -d -f -x && \
        phpize --clean && \
        phpize && \
        ./configure \
            --enable-sockets \
            --disable-mysqlnd \
            --enable-http2 \
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
