FROM fedora:34

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        coturn && \
    dnf clean all

COPY rootfs/ /

CMD ["/usr/local/bin/coturn.sh"]
