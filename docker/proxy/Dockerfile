FROM fedora:31

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

ENV container docker
ENV SYSTEMD_PAGER=''

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        bash-completion \
        bind-utils \
        certbot \
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
        NetworkManager \
        NetworkManager-tui \
        network-scripts \
        nginx \
        nmap-ncat \
        openssh-clients \
        openssh-server \
        procps-ng \
        python3-certbot-nginx \
        strace \
        systemd-udev \
        tcpdump \
        telnet \
        traceroute \
        vim-enhanced \
        wget && \
    dnf clean all

COPY rootfs/ /

RUN systemctl enable nginx

CMD ["/lib/systemd/systemd", "--system"]
ENTRYPOINT "/lib/systemd/systemd"
