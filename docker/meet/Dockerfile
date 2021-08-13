FROM fedora:34

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        bash-completion \
        bind-utils \
        coturn \
        curl \
        dhcp-client \
        git \
        iproute \
        iptraf-ng \
        iputils \
        less \
        lsof \
        mtr \
        net-tools \
        npm nodejs python3 make gcc g++ \
        NetworkManager \
        NetworkManager-tui \
        network-scripts \
        nmap-ncat \
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

# 3443/tcp (default https webserver and signaling - adjustable in server/config.js)
# 4443/tcp (default npm start port for developing with live browser reload, not needed in production environments - adjustable in app/package.json)
# 40000-49999/udp/tcp (media ports - adjustable in server/config.js)

COPY init.sh /init.sh
CMD [ "/init.sh" ]
