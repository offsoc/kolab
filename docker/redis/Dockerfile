FROM fedora:35

ENV container docker


RUN id default || (groupadd -g 1001 default && useradd -d /opt/app-root/ -u 1001 -g 1001 default)

# Create user for redis that has known UID
# We need to do this before installing the RPMs which would create user with random UID
# The UID is the one used by the default user from the parent layer (1001),
# and since the user exists already, do not create a new one, but only rename
# the existing
RUN getent group  redis &> /dev/null || groupadd -r redis &> /dev/null && \
    usermod -l redis -aG redis -c 'Redis Server' default &> /dev/null 

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
COPY init.sh /init.sh

RUN mkdir -p /var/lib/redis/data && chown -R redis.0 /var/lib/redis && \
    chgrp -R 0 /init.sh /etc/redis/redis.conf && \
    chmod -R g=u /init.sh /etc/redis/redis.conf

USER 1001
WORKDIR /

CMD ["/init.sh"]

EXPOSE 6379/tcp
