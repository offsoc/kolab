FROM fedora:31

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@apheleia-it.ch>

LABEL io.k8s.description="Platform for serving PHP applications under Swoole" \
      io.k8s.display-name="Swoole 4.4.x" \
      io.openshift.expose-services="8000:http" \
      io.openshift.tags="builder,php,swoole"

ENV SWOOLE_VERSION=4.4.x

RUN dnf -y install \
        composer \
        diffutils \
        file \
        git \
        make \
        npm \
        openssl-devel \
        php-cli \
        php-common \
        php-devel \
        php-ldap \
        php-opcache \
        php-pecl-apcu \
        re2c && \
    git clone -b v4.4.x https://github.com/swoole/swoole-src.git/ /swoole-src.git/ && \
        cd /swoole-src.git/ && \
        git clean -d -f -x && \
        phpize --clean && \
        phpize && \
        ./configure \
            --enable-sockets \
            --disable-mysqlnd \
            --enable-http2 \
            --enable-openssl && \
            --enable-debug \
            --enable-debug-log \
            --enable-trace-log && \
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

RUN groupadd -g 1001 default && \
    useradd -d /opt/app-root/ -u 1001 -g 1001 default

USER default

WORKDIR /opt/app-root/

COPY /rootfs /

EXPOSE 8000

CMD [ "/usr/local/bin/usage" ]
