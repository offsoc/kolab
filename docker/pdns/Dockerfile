FROM apheleia/almalinux9

RUN dnf -y install \
    --setopt 'tsflags=nodocs' \
    iproute \
    iptables \
    iputils \
    net-tools \
    pdns \
    pdns-backend-mysql \
    pdns-recursor \
    pdns-tools \
    which && \
    dnf clean all

COPY pdns.conf /etc/pdns/pdns.conf
COPY recursor.conf /etc/pdns-recursor/recursor.conf
COPY init.sh /init.sh

CMD ["/init.sh"]

EXPOSE 53 53/udp
