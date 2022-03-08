FROM fedora:35

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

ENV container docker

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        nginx \
        nginx-mod-mail && \
    dnf clean all

COPY nginx.conf /etc/nginx/nginx.conf
ARG APP_WEBSITE_DOMAIN
RUN sed -i -r -e "s|^.*auth_http_header.*$|    auth_http_header    Host services.$APP_WEBSITE_DOMAIN;|g" /etc/nginx/nginx.conf

# Forward request logs to Docker log collector
RUN ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

STOPSIGNAL SIGTERM

CMD ["nginx", "-g", "daemon off;"]

EXPOSE 110/tcp 143/tcp 993/tcp 995/tcp
