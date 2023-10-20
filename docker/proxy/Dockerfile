FROM fedora:35

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

ENV container docker

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

CMD ["/init.sh"]

EXPOSE 25/tcp 80/tcp 443/tcp 465/tcp 587/tcp 143/tcp 144/tcp 993/tcp
