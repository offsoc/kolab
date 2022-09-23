FROM fedora:35

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

ENV container docker

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        nginx \
        nginx-mod-mail && \
    dnf clean all

COPY rootfs/ /
ARG APP_WEBSITE_DOMAIN
ARG SSL_CERTIFICATE
ARG SSL_CERTIFICATE_KEY
RUN sed -i -r -e "s|APP_WEBSITE_DOMAIN|$APP_WEBSITE_DOMAIN|g" /etc/nginx/nginx.conf
RUN sed -i -r -e "s|SSL_CERTIFICATE_CERT|$SSL_CERTIFICATE|g" /etc/nginx/nginx.conf
RUN sed -i -r -e "s|SSL_CERTIFICATE_KEY|$SSL_CERTIFICATE_KEY|g" /etc/nginx/nginx.conf

# Forward request logs to Docker log collector
RUN ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

STOPSIGNAL SIGTERM

CMD ["nginx", "-g", "daemon off;"]

EXPOSE 80/tcp 443/tcp 465/tcp 587/tcp 143/tcp 144/tcp 993/tcp
