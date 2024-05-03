FROM kolab-ci/almalinux9

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        nginx \
        nginx-mod-mail \
        nginx-mod-stream && \
    dnf clean all

# ENV APP_WEBSITE_DOMAIN
# ENV SSL_CERTIFICATE
# ENV SSL_CERTIFICATE_KEY

COPY rootfs/ /

RUN chgrp -R 0 /etc/nginx /run /var/log/nginx && \
    chmod -R g=u /etc/nginx /run /var/log/nginx && \
    chown -R 1001:0 /etc/nginx /run /var/log/nginx

STOPSIGNAL SIGTERM

ENV WEBAPP_BACKEND http://webapp:8000
ENV MEET_BACKEND https://meet:12443
ENV ROUNDCUBE_BACKEND http://roundcube:8080
ENV DAV_BACKEND http://imap:11080/dav
ENV COLLABORA_BACKEND http://collabora:9980
ENV WEBMAIL_PATH /roundcubemail

USER 1001

CMD ["/init.sh"]

EXPOSE 6443/tcp 6465/tcp 6587/tcp 6143/tcp 6144/tcp 6993/tcp
