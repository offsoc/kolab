FROM apheleia/almalinux9

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        nginx \
        nginx-mod-mail && \
    dnf clean all

# ENV APP_WEBSITE_DOMAIN
# ENV SSL_CERTIFICATE
# ENV SSL_CERTIFICATE_KEY

COPY rootfs/ /

# Forward request logs to Docker log collector
RUN ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

STOPSIGNAL SIGTERM

ENV WEBAPP_BACKEND http://webapp:8000
ENV MEET_BACKEND https://meet:12443
ENV ROUNDCUBE_BACKEND http://roundcube:8080
ENV DAV_BACKEND http://imap:11080/dav
ENV COLLABORA_BACKEND http://collabora:9980
ENV WEBMAIL_PATH /roundcubemail


CMD ["/init.sh"]

EXPOSE 6443/tcp 6465/tcp 6587/tcp 6143/tcp 6144/tcp 6993/tcp
