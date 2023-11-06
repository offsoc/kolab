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

RUN dnf -y group install "Development Tools"; \
    dnf -y builddep cyrus-imapd; \
    dnf -y install git perl-devel cyrus-sasl cyrus-sasl-plain procps-ng iputils bind-utils sudo

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

ADD init.sh /init.sh

VOLUME [ "/var/spool/imap" ]
VOLUME [ "/var/lib/imap" ]

RUN id default || (groupadd -g 1001 default && useradd -u 1001 -g 1001 default)

RUN PATHS=(/run /run/saslauthd /var/run /var/lib/imap /var/spool/imap /etc/pki/cyrus-imapd) && \
    mkdir -p ${PATHS[@]} && \
    chmod 777 ${PATHS[@]} && \
    chown -R 1001:0 ${PATHS[@]} && \
    chmod -R g=u ${PATHS[@]}

RUN PATHS=(/etc /etc/passwd /etc/saslauthd.conf /etc/cyrus.conf /etc/imapd.conf) && \
    chown 1001:0 ${PATHS[@]} && \
    chmod g=u ${PATHS[@]}

USER 1001

CMD ["/init.sh"]

EXPOSE 11143/tcp 11993/tcp 11080/tcp 11443/tcp 11024/tcp 4190/tcp
