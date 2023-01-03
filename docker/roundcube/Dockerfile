FROM quay.io/centos/centos:stream9

MAINTAINER Christian Mollekopf <mollekopf@apheleia-it.ch>

ENV HOME=/opt/app-root/src

LABEL io.k8s.description="Platform for serving PHP roundcube applications" \
      io.k8s.display-name="Roundcube" \
      io.openshift.expose-services="80:http" \
      io.openshift.tags="builder,php,apache"

#FIXME switch to centos9 because we need the php extensions for libkolabxml and libkolab
RUN dnf -y update

# Add EPEL.
RUN dnf -y install 'dnf-command(config-manager)' && \
    dnf config-manager --set-enabled crb && \
    dnf -y install \
        epel-release epel-next-release && \
    dnf clean all

# Add the EPEL key.
RUN rpm --import /etc/pki/rpm-gpg/RPM-GPG-KEY-EPEL-9


# Add kolab
RUN rpm --import https://mirror.apheleia-it.ch/repos/Kolab:/16/key.asc && \
    rpm -Uvh https://mirror.apheleia-it.ch/repos/Kolab:/16/kolab-16-for-el9stream.rpm

# Install php modules
RUN sed -i -e '/^ssl/d' /etc/yum.repos.d/kolab*.repo && \
    dnf config-manager --enable kolab-16-testing &&\
    dnf -y --setopt tsflags= install php-kolab php-kolabformat &&\
    dnf clean all

RUN dnf -y install \
        composer \
        diffutils \
        file \
        git \
        make \
        unzip \
        curl-minimal \
        mariadb \
        which \
        rsync \
        openssl-devel \
        httpd \
        patch \
        php-cli \
        php-common \
        php-devel \
        php-ldap \
        php-opcache \
        php-pecl-apcu \
        php-mysqlnd \
        php-gd \
        php-fpm \
        php-pear \
        ImageMagick \
        re2c \
        npm \
        wget && \
    dnf clean all


RUN npm install -g less less-plugin-clean-css

WORKDIR ${HOME}

COPY /rootfs /
RUN /opt/app-root/src/build.sh 

EXPOSE 80

# https://httpd.apache.org/docs/2.4/stopping.html#gracefulstop
STOPSIGNAL SIGWINCH

CMD [ "/opt/app-root/src/init.sh" ]
