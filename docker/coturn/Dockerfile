FROM fedora:31

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

ENV container docker
ENV SYSTEMD_PAGER=''

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        bash-completion \
        bind-utils \
        coturn \
        curl \
        dhcp-client \
        iproute \
        iptraf-ng \
        iputils \
        less \
        lsof \
        mtr \
        net-tools \
        NetworkManager \
        NetworkManager-tui \
        network-scripts \
        nmap-ncat \
        openssh-clients \
        openssh-server \
        procps-ng \
        strace \
        systemd-udev \
        tcpdump \
        telnet \
        traceroute \
        vim-enhanced \
        wget && \
    dnf clean all

COPY rootfs/ /

RUN chmod 770 /etc/coturn/ && chmod 660 /etc/coturn/turnserver.conf

RUN systemctl enable coturn

CMD ["/lib/systemd/systemd", "--system"]
ENTRYPOINT "/lib/systemd/systemd"
