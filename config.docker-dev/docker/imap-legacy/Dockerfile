FROM quay.io/centos/centos:7

LABEL maintainer="contact@apheleia-it.ch"
LABEL dist=centos7
LABEL tier=${TIER}

ENV SYSTEMD_PAGER=''
ENV DISTRO=centos7
ENV LANG=en_US.utf8
ENV LC_ALL=en_US.utf8

# Add EPEL.
RUN rpm -Uhv https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm && \
    yum -y install yum-plugin-priorities && \
    rpm --import https://mirror.apheleia-it.ch/repos/Kolab:/16/key.asc && \
    yum -y install https://mirror.apheleia-it.ch/repos/Kolab:/16/kolab-16-for-el7.rpm && \
    sed -i -e '/^ssl/d' /etc/yum.repos.d/kolab*.repo && \
    yum -y --setopt tsflags= install patch cyrus-imapd cyrus-sasl cyrus-sasl-plain &&\
    yum clean all

RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config 2>/dev/null || :

WORKDIR /root/

COPY cyrus.conf /etc/cyrus.conf
COPY imapd.conf /etc/imapd.conf
COPY imapd.annotations.conf /etc/imapd.annotations.conf
COPY saslauthd.conf /etc/saslauthd.conf

ADD kolab.hosted.com.cert /etc/pki/tls/certs/kolab.hosted.com.cert
ADD kolab.hosted.com.chain.pem /etc/pki/tls/certs/kolab.hosted.com.chain.pem
ADD kolab.hosted.com.key /etc/pki/tls/certs/kolab.hosted.com.key
RUN mkdir -p /etc/pki/cyrus-imapd/ && cat /etc/pki/tls/certs/kolab.hosted.com.cert /etc/pki/tls/certs/kolab.hosted.com.chain.pem /etc/pki/tls/certs/kolab.hosted.com.key > /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem && \
    chown cyrus:mail /etc/pki/cyrus-imapd/cyrus-imapd.bundle.pem
RUN mkdir /run/cyrus && chmod 777 /run/cyrus


RUN sed -i "s/MECH=.*/MECH=httpform/" /etc/sysconfig/saslauthd
RUN systemctl enable cyrus-imapd && systemctl enable saslauthd

CMD ["/lib/systemd/systemd"]

EXPOSE 143/tcp 993/tcp 80/tcp 443/tcp
