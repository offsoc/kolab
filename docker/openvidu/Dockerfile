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
    dnf clean all

RUN git clone --branch v2.14.0 https://github.com/OpenVidu/openvidu.git /openvidu.git && \
    cd /openvidu.git/ && \
    mvn clean install -DskipTests && \
    cp /openvidu.git/openvidu-server/target/openvidu-server-2.14.0.jar /root/ && \
    cd /root/ && \
    rm -rf /openvidu.git/ /root/.m2/

COPY rootfs/ /

RUN useradd openvidu

RUN mv /root/openvidu-server-*.jar /home/openvidu/openvidu-server.jar

RUN chown -R openvidu:openvidu /home/openvidu/

RUN systemctl enable openvidu

CMD ["/lib/systemd/systemd", "--system"]
ENTRYPOINT "/lib/systemd/systemd"
