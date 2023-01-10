FROM apheleia/swoole:4.8.x

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@apheleia-it.ch>

USER root

RUN dnf -y install findutils gnupg2 git rsync

EXPOSE 8000

COPY build.sh /build.sh
RUN /build.sh
COPY init.sh /init.sh

CMD [ "/init.sh" ]
