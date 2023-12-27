FROM apheleia/swoole:latest

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@apheleia-it.ch>

USER root

RUN dnf -y install findutils gnupg2 git rsync procps-ng php-sodium

EXPOSE 8000

ARG GIT_REF=master
ARG GIT_REMOTE=https://git.kolab.org/source/kolab.git
ARG CONFIG=config.prod
COPY build.sh /build.sh
RUN /build.sh
COPY init.sh /init.sh
COPY update.sh /update.sh

ENV KOLAB_ROLE=combined

CMD [ "/init.sh" ]
