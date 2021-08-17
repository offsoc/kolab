FROM fedora:31

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

ENV container docker
ENV SYSTEMD_PAGER=''

ARG NGINX_AUTH_WEBHOOK

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
        nginx-mod-mail \
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

RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config 2>/dev/null || :

COPY nginx.conf /etc/nginx/nginx.conf
RUN sed -i -r -e "s|^.*auth_http.*$|    auth_http   $NGINX_AUTH_WEBHOOK;|g" /etc/nginx/nginx.conf

RUN systemctl enable nginx

CMD ["/lib/systemd/systemd", "--system"]
ENTRYPOINT "/lib/systemd/systemd"

EXPOSE 110/tcp 143/tcp 993/tcp 995/tcp
