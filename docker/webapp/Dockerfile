FROM apheleia/swoole:4.8.x

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@apheleia-it.ch>

USER root

RUN dnf -y install findutils gnupg2

EXPOSE 8000

COPY init.sh /init.sh

CMD [ "/init.sh" ]
