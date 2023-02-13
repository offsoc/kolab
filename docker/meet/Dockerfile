FROM fedora:37

MAINTAINER Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        npm nodejs python3 python3-pip meson ninja-build make gcc g++ git && \
    dnf clean all

ARG GIT_REF=master
COPY build.sh /build.sh
RUN /build.sh
COPY init.sh /init.sh
CMD [ "/init.sh" ]
