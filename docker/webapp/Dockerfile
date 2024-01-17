FROM apheleia/swoole:latest

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@apheleia-it.ch>

USER root

RUN dnf -y install findutils gnupg2 git rsync procps-ng php-sodium

EXPOSE 8000

ARG GIT_REF=master
ARG GIT_REMOTE=https://git.kolab.org/source/kolab.git
ARG CONFIG=config.prod
COPY build.sh /build.sh
RUN /build.sh && \
    chgrp -R 0 /opt/app-root/src && \
    chmod -R g=u /opt/app-root/src && \
    chown -R 1001:0 /opt/app-root/src

COPY init.sh /init.sh
COPY update.sh /update.sh

USER 1001

ENV KOLAB_ROLE=combined

CMD [ "/init.sh" ]
