FROM apheleia/almalinux9

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        coturn && \
    dnf clean all && \
    chgrp -R 0 /tmp && \
    chmod -R g=u /tmp && \
    chmod -R 777 /tmp /var/lib/coturn && \
    chown -R 1001:0 /tmp /var/lib/coturn

COPY rootfs/ /

USER 1001

EXPOSE 3478 5349

CMD ["/usr/local/bin/coturn.sh"]
