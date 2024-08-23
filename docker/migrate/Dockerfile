FROM kolab-webapp:latest

USER root

RUN dnf -y install \
        --setopt=install_weak_deps=False \
        --setopt 'tsflags=nodocs' \
        gcc-c++ \
        libgsf \
        gettext \
        libtool \
        automake \
        gd-devel zlib-devel boost-devel libgsf-devel gettext-devel && \
    dnf clean all

ADD build.sh /build.sh
RUN /build.sh
COPY /rootfs /
