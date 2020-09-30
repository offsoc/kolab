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
        strace \
        systemd-udev \
        tcpdump \
        telnet \
        traceroute \
        vim-enhanced \
        wget && \
    dnf clean all && \
    mkdir -p /src/ && \
    git clone --branch v2.15.0 https://github.com/OpenVidu/openvidu.git /src/openvidu.git && \
        cd /src/openvidu.git/ && \
        mvn clean install -DskipTests && \
        mkdir -p /usr/share/openvidu/ && \
        useradd openvidu && \
        mv -v \
            /src/openvidu.git/openvidu-server/target/openvidu-server-2.15.0.jar \
            /home/openvidu/openvidu-server.jar && \
        chown -R openvidu:openvidu /home/openvidu/ && \
        rm -rvf /src/ /root/.m2/ && \

USER openvidu

CMD [ \
        "/usr/bin/java", \
        "-Dserver.port=8080", \
        "-Dserver.ssl.enabled=false", \
        "-jar", \
        "/home/openvidu/openvidu-server.jar" \
    ]
