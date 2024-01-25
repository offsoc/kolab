FROM fedora:35

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        coturn && \
    dnf clean all

COPY rootfs/ /

RUN chgrp -R 0 /tmp && \
    chmod -R g=u /tmp

RUN chmod -R 777 /tmp /var/lib/coturn
RUN chown -R 1001:0 /tmp /var/lib/coturn

USER 1001

EXPOSE 3478 5349

CMD ["/usr/local/bin/coturn.sh"]
