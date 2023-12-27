FROM almalinux:9

MAINTAINER Christian Mollekopf <mollekopf@apheleia-it.ch>

ENV HOME=/opt/app-root/src

LABEL io.k8s.description="Platform for serving PHP roundcube applications" \
      io.k8s.display-name="Roundcube" \
      io.openshift.expose-services="80:http" \
      io.openshift.tags="builder,php,apache"

RUN dnf -y update

# Add EPEL.
RUN dnf -y install 'dnf-command(config-manager)' && \
    dnf config-manager --set-enabled crb && \
    dnf -y install epel-release && \
    dnf -y install epel-next-release && \
    dnf clean all

# Add the EPEL key.
RUN rpm --import /etc/pki/rpm-gpg/RPM-GPG-KEY-EPEL-9


# Add kolab
RUN rpm --import https://mirror.apheleia-it.ch/repos/Kolab:/16/key.asc && \
    rpm -Uvh https://mirror.apheleia-it.ch/repos/Kolab:/16/kolab-16-for-el9.rpm

# Install php modules
RUN sed -i -e '/^ssl/d' /etc/yum.repos.d/kolab*.repo && \
    dnf config-manager --enable kolab-16 &&\
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
        # ImageMagick \
        re2c \
        npm \
        wget && \
        dnf -y install procps-ng iputils bind-utils sudo telnet mc && \
    dnf clean all

RUN dnf -y install --disablerepo epel-next ImageMagick

RUN npm install -g less less-plugin-clean-css

WORKDIR ${HOME}

COPY /rootfs /

ARG GIT_REF_ROUNDCUBEMAIL=dev/kolab-1.5
ARG GIT_REMOTE_ROUNDCUBEMAIL=https://git.kolab.org/source/roundcubemail.git
ARG GIT_REF_ROUNDCUBEMAIL_PLUGINS=master
ARG GIT_REMOTE_ROUNDCUBEMAIL_PLUGINS=https://git.kolab.org/diffusion/RPK/roundcubemail-plugins-kolab.git
ARG GIT_REF_CHWALA=master
ARG GIT_REMOTE_CHWALA=https://git.kolab.org/diffusion/C/chwala.git
ARG GIT_REF_SYNCROTON=master
ARG GIT_REMOTE_SYNCROTON=https://git.kolab.org/diffusion/S/syncroton.git
ARG GIT_REF_AUTOCONF=master
ARG GIT_REMOTE_AUTOCONF=https://git.kolab.org/diffusion/AC/autoconf.git
ARG GIT_REF_IRONY=master
ARG GIT_REMOTE_IRONY=https://git.kolab.org/source/iRony.git
ARG GIT_REF_FREEBUSY=master
ARG GIT_REMOTE_FREEBUSY=https://git.kolab.org/diffusion/F/freebusy.git

VOLUME /data

RUN /opt/app-root/src/build.sh && \
    chgrp -R 0 /opt/app-root/src && \
    chmod -R g=u /opt/app-root/src && \
    mkdir -p /run/php-fpm && \
    chmod 777 /run/php-fpm && \
    mkdir -p /run/httpd && \
    chmod 777 /run/httpd && \
    mkdir -p /data && \
    chmod 777 /data && \
    chmod -R 777 /etc/httpd /var/log/httpd /var/lib/httpd /data && \
    chown -R 1001:0 /opt/app-root/src /data

USER 1001

EXPOSE 8080

# https://httpd.apache.org/docs/2.4/stopping.html#gracefulstop
STOPSIGNAL SIGWINCH

CMD [ "/opt/app-root/src/init.sh" ]
