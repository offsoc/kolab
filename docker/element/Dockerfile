FROM apheleia/almalinux9

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        wget \
        nginx && \
    dnf clean all

ARG ELEMENTWEB_VERSION="v1.11.35"

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        nginx \
        wget \
    && dnf clean all

# Forward request logs to Docker log collector
RUN ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

RUN wget "https://github.com/vector-im/element-web/releases/download/${ELEMENTWEB_VERSION}/element-${ELEMENTWEB_VERSION}.tar.gz" -O "/opt/element-${ELEMENTWEB_VERSION}.tar.gz" && \
    tar -xf /opt/element-${ELEMENTWEB_VERSION}.tar.gz -C /opt && \
    ln -s /opt/element-${ELEMENTWEB_VERSION} /opt/element-web && \
    rm /opt/element-${ELEMENTWEB_VERSION}.tar.gz

COPY rootfs/ /

STOPSIGNAL SIGTERM

RUN id default || (groupadd -g 1001 default && useradd -d /opt/app-root/ -u 1001 -g 1001 default)

RUN PATHS=(/opt/app-root/src /opt/element-web /etc/nginx /var/lib/nginx) && \
    mkdir -p ${PATHS[@]} && \
    chmod -R 777 ${PATHS[@]} && \
    chown -R 1001:0 ${PATHS[@]} && \
    chmod -R g=u ${PATHS[@]}

USER 1001

CMD ["/init.sh"]

EXPOSE 8880/tcp
