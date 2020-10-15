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
        strace \
        systemd-udev \
        tcpdump \
        telnet \
        traceroute \
        vim-enhanced \
        wget && \
    dnf clean all

COPY rootfs/ /

RUN /usr/local/sbin/coturn.sh

RUN chmod 755 /etc/coturn/ && chmod 644 /etc/coturn/turnserver.conf

CMD [ \
        "/usr/bin/turnserver", \
        "-c", "/etc/coturn/turnserver.conf" \
    ]
