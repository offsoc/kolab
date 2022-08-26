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
    pdns-tools \
    procps-ng \
    vim-enhanced \
    wget \
    which && \
    dnf clean all

COPY pdns.conf /etc/pdns/pdns.conf

RUN systemctl enable pdns

WORKDIR /root/

CMD ["/lib/systemd/systemd", "--system"]
