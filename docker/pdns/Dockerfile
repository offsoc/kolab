FROM fedora:35

ENV container docker
ENV SYSTEMD_PAGER=''

RUN dnf -y install \
    --setopt 'tsflags=nodocs' \
    bind-utils \
    cronie \
    iproute \
    iptables \
    net-tools \
    pdns \
    pdns-backend-mysql \
    pdns-recursor \
    pdns-tools \
    procps-ng \
    vim-enhanced \
    wget \
    which && \
    dnf clean all

COPY pdns.conf /etc/pdns/pdns.conf
COPY recursor.conf /etc/pdns-recursor/recursor.conf

RUN systemctl enable pdns  && systemctl enable pdns-recursor

# This is how we could run pdns without systemd
# ENV PDNS_guardian=yes \
#     PDNS_setuid=pdns \
#     PDNS_setgid=pdns \
#     PDNS_launch=gmysql

# CMD ["/usr/sbin/pdns_server", "--guardian=no", "--daemon=no", "--disable-syslog", "--log-timestamp=no", "--write-pid=no"]
CMD ["/lib/systemd/systemd", "--system"]

EXPOSE 9953 9953/udp
