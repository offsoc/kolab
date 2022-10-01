FROM almalinux:8

LABEL maintainer="contact@apheleia-it.ch"
LABEL dist=centos8
LABEL tier=${TIER}

ENV SYSTEMD_PAGER=''
ENV DISTRO=centos8
ENV LANG=en_US.utf8
ENV LC_ALL=en_US.utf8

# Add EPEL.
RUN dnf -y install dnf-plugin-config-manager && \
    dnf config-manager --set-enabled powertools && \
    dnf -y install epel-release && \
    dnf -y module enable 389-ds:1.4/default && \
    dnf -y module enable mariadb:10.3 && \
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

RUN dnf -y install git
RUN dnf -y group install "Development Tools"
RUN git clone https://github.com/cmollekopf/cyrus-imapd

RUN dnf -y install autoconf automake bison cyrus-sasl-devel flex gcc gperf jansson-devel libbsd-devel libtool libicu-devel libuuid-devel openssl-devel pkgconfig sqlite-devel brotli-devel libical-devel libxml2-devel libnghttp2-devel shapelib  zlib-devel pcre-devel

RUN dnf -y install perl-devel
RUN dnf -y install cyrus-imapd  cyrus-sasl cyrus-sasl-plain
# wslay-devel
#libchardet-devel 
 # cld2-devel
 #
COPY cyrus.conf /etc/cyrus.conf
COPY imapd.conf /etc/imapd.conf
COPY imapd.annotations.conf /etc/imapd.annotations.conf
COPY saslauthd.conf /etc/saslauthd.conf

ARG IMAP_ADMIN_LOGIN
ARG IMAP_ADMIN_PASSWORD
RUN sed -i -r \
    -e "s|IMAP_ADMIN_LOGIN|$IMAP_ADMIN_LOGIN|g" \
    -e "s|IMAP_ADMIN_PASSWORD|$IMAP_ADMIN_PASSWORD|g" \
    /etc/imapd.conf

RUN cd cyrus-imapd && \
    git checkout dev/kolab-3.6 && \
    autoreconf -i && \
    ./configure CFLAGS="-W -Wno-unused-parameter -g -O0 -Wall -Wextra -Werror -fPIC" --enable-murder --enable-http --enable-calalarmd --enable-autocreate --enable-idled --with-openssl=yes --enable-replication --prefix=/usr && \
    make -j6 && \
    make install

COPY cyrus-imapd.service /etc/systemd/system/cyrus-imapd.service


# RUN useradd -g mail cyrus

ADD kolab.hosted.com.cert /etc/pki/tls/certs/kolab.hosted.com.cert
ADD kolab.hosted.com.chain.pem /etc/pki/tls/certs/kolab.hosted.com.chain.pem
ADD kolab.hosted.com.key /etc/pki/tls/certs/kolab.hosted.com.key
RUN mkdir -p /etc/pki/cyrus-imapd/ && cat /etc/pki/tls/certs/kolab.hosted.com.cert /etc/pki/tls/certs/kolab.hosted.com.chain.pem /etc/pki/tls/certs/kolab.hosted.com.key > /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem && \
    chown cyrus:mail /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem

RUN sed -i "s/MECH=.*/MECH=httpform/" /etc/sysconfig/saslauthd
RUN systemctl enable cyrus-imapd && systemctl enable saslauthd
RUN echo "csync           2005/tcp" >> /etc/services

CMD ["/lib/systemd/systemd"]

EXPOSE 143/tcp 993/tcp 80/tcp 443/tcp
