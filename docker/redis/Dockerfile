FROM fedora:34

ENV container docker
ENV SYSTEMD_PAGER=''

RUN dnf -y install \
    --setopt 'tsflags=nodocs' \
    bind-utils \
    cronie \
    iproute \
    iptables \
    net-tools \
    procps-ng \
    redis \
    suricata \
    vim-enhanced \
    wget \
    which && \
    dnf clean all

COPY redis.conf /etc/redis.conf

RUN systemctl enable redis

WORKDIR /root/

CMD ["/usr/bin/redis-server"]
