FROM apheleia/swoole:4.8.x

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@apheleia-it.ch>

USER root

RUN dnf -y install findutils php-phpunit-PHPUnit chromium php-xdebug

RUN mkdir /src/ && chown 1001:100 /src/

RUN usermod -G wheel default
RUN echo '%wheel  ALL=(ALL)       NOPASSWD: ALL' >> /etc/sudoers
RUN chmod 777 /opt/app-root/src

USER default

EXPOSE 8000

COPY init.sh /init.sh
COPY lint.sh /lint.sh
CMD [ "/init.sh" ]
