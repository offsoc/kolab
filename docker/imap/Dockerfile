FROM almalinux:8

LABEL maintainer="contact@apheleia-it.ch"
LABEL dist=centos8
LABEL tier=${TIER}

ENV DISTRO=centos8
# ENV LANG=en_US.utf8
# ENV LC_ALL=en_US.utf8

# Add EPEL.
RUN dnf -y install dnf-plugin-config-manager && \
    dnf config-manager --set-enabled powertools && \
    dnf -y install epel-release && \
    dnf -y install iputils vim-enhanced bind-utils && \
    dnf clean all
RUN rpm --import /etc/pki/rpm-gpg/RPM-GPG-KEY-EPEL-8

# Install kolab
RUN rpm --import https://mirror.apheleia-it.ch/repos/Kolab:/16/key.asc && \
    rpm -Uvh https://mirror.apheleia-it.ch/repos/Kolab:/16/kolab-16-for-el8.rpm
RUN sed -i -e '/^ssl/d' /etc/yum.repos.d/kolab*.repo && \
    dnf config-manager --enable kolab-16-testing &&\
    dnf -y --setopt tsflags= install patch &&\
    dnf clean all

RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config 2>/dev/null || :

WORKDIR /root/

RUN dnf -y group install "Development Tools"
RUN dnf -y builddep cyrus-imapd
RUN dnf -y install git perl-devel cyrus-sasl cyrus-sasl-plain procps-ng iputils bind-utils sudo rsyslog

RUN git clone --branch dev/mollekopf https://git.kolab.org/source/cyrus-imapd && \
    cd cyrus-imapd && \
    autoreconf -i && \
    ./configure CFLAGS="-W -Wno-unused-parameter -g -O0 -Wall -Wextra -Werror -fPIC" --enable-murder --enable-http --enable-calalarmd --enable-autocreate --enable-idled --with-openssl=yes --enable-replication --prefix=/usr && \
    make -j6 && \
    make install

COPY cyrus.conf /etc/cyrus.conf
COPY imapd.conf /etc/imapd.conf
COPY imapd.annotations.conf /etc/imapd.annotations.conf
COPY saslauthd.conf /etc/saslauthd.conf
COPY rsyslog.conf /etc/rsyslog.conf

ADD kolab.hosted.com.cert /etc/pki/tls/certs/kolab.hosted.com.cert
ADD kolab.hosted.com.chain.pem /etc/pki/tls/certs/kolab.hosted.com.chain.pem
ADD kolab.hosted.com.key /etc/pki/tls/certs/kolab.hosted.com.key
ADD init.sh /init.sh

RUN mkdir -p /etc/pki/cyrus-imapd/ && cat /etc/pki/tls/certs/kolab.hosted.com.cert /etc/pki/tls/certs/kolab.hosted.com.chain.pem /etc/pki/tls/certs/kolab.hosted.com.key > /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem && \
    chown 1001:0 /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem

# RUN chgrp -R 0 /opt/app-root/src && \
#     chmod -R g=u /opt/app-root/src

RUN mkdir -p /run/saslauthd && \
    chmod 777 /run/saslauthd &&  \
    chmod -R 777 /run && \
    mkdir -p /var/run && \
    chmod -R 777 /var/run && \
    mkdir -p /var/lib/imap && \
    chmod 777 /var/lib/imap && \
    mkdir -p /var/spool/imap && \
    chmod 777 /var/spool/imap

VOLUME [ "/var/spool/imap" ]
VOLUME [ "/var/lib/imap" ]

RUN chown -R 1001:0 /etc /var/lib/imap /var/spool/imap /var/run /run && \
    chmod -R g=u /etc /var/lib/imap /var/spool/imap /var/run /run

RUN echo 'default ALL=NOPASSWD: ALL' >> /etc/sudoers && \
    chown root:root /etc/sudoers /etc/sudo.conf /etc/sudoers.d

RUN id default || (groupadd -g 1001 default && useradd -d /opt/app-root/ -u 1001 -g 1001 default)
USER 1001


CMD ["/init.sh"]

EXPOSE 11143/tcp 11993/tcp 11080/tcp 11443/tcp 11024/tcp
