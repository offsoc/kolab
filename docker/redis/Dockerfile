FROM fedora:35

ENV container docker

RUN dnf -y install \
    --setopt 'tsflags=nodocs' \
    bind-utils \
    cronie \
    iproute \
    iptables \
    net-tools \
    procps-ng \
    redis \
    vim-enhanced \
    wget \
    which && \
    dnf clean all

COPY redis.conf /etc/redis/redis.conf

WORKDIR /root/

CMD ["/usr/bin/redis-server", "/etc/redis/redis.conf"]

EXPOSE 6379/tcp
