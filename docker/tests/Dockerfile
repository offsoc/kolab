FROM kolab-webapp:latest

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@apheleia-it.ch>

USER root

RUN dnf -y install findutils php-phpunit-PHPUnit chromium php-xdebug






COPY init.sh /init.sh
COPY lint.sh /lint.sh
CMD [ "/init.sh" ]
