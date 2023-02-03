FROM apheleia/swoole:latest

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@apheleia-it.ch>

USER root

RUN dnf -y install findutils gnupg2 git rsync procps-ng

EXPOSE 8000

ARG GIT_REF=master
COPY build.sh /build.sh
RUN /build.sh
COPY init.sh /init.sh
COPY update.sh /update.sh

CMD [ "/init.sh" ]
