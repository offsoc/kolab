FROM fedora:34

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        npm nodejs python3 make gcc g++ && \
    dnf clean all

COPY init.sh /init.sh
CMD [ "/init.sh" ]
