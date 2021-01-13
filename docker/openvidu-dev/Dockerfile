FROM fedora:31

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

ENV CERTIFICATE_TYPE=selfsigned
ENV DOMAIN_OR_PUBLIC_IP=localhost.localdomain
ENV OPENVIDU_SECRET=MY_SECRET

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
        java-1.8.0-openjdk \
        less \
        lsof \
        maven \
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

COPY init.sh /init.sh
CMD [ "/init.sh" ]
