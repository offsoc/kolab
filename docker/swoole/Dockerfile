FROM fedora:37

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@apheleia-it.ch>

ARG SWOOLE_VERSION=v5.0.2
ENV HOME=/opt/app-root/src

LABEL io.k8s.description="Platform for serving PHP applications under Swoole" \
      io.k8s.display-name="Swoole ${SWOOLE_VERSION}" \
      io.openshift.expose-services="8000:http" \
      io.openshift.tags="builder,php,swoole"

RUN dnf -y update && \
    dnf -y install \
        composer \
        diffutils \
        file \
        git \
        make \
        npm \
        openssl-devel \
        openssl \
        curl \
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
