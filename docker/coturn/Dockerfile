FROM fedora:31

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

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
        redis \
        strace \
        systemd-udev \
        tcpdump \
        telnet \
        traceroute \
        vim-enhanced \
        wget && \
    dnf clean all

COPY rootfs/ /

CMD ["/usr/local/bin/coturn.sh"]
