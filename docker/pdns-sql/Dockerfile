FROM fedora:35

ENV container docker

RUN dnf -y install \
    --setopt 'tsflags=nodocs' \
    bind-utils \
    cronie \
    iproute \
    iptables \
    net-tools \
    pdns \
    pdns-backend-mysql \
    pdns-tools \
    procps-ng \
    vim-enhanced \
    wget \
    which && \
    dnf clean all

COPY pdns.conf /etc/pdns/pdns.conf

ENV PDNS_guardian=yes \
    PDNS_setuid=pdns \
    PDNS_setgid=pdns \
    PDNS_launch=gmysql

CMD ["/usr/sbin/pdns_server", "--guardian=no", "--daemon=no", "--disable-syslog", "--log-timestamp=no", "--write-pid=no"]

EXPOSE 9953 9953/udp
