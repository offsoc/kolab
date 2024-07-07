FROM apheleia/almalinux9

ENV HOME=/opt/app-root/src


RUN dnf -y install \
        --setopt=install_weak_deps=False \
        --setopt 'tsflags=nodocs' \
        libtiff-devel \
        libjpeg-devel \
        libzip-devel \
        freetype-devel \
        lcms2 \
        libwebp-devel \
        tcl-devel \
        tk-devel \
        python3 \
        python3-pip \
        libffi-devel \
        openssl-devel \
        sed \
        wget && \
        pip3 install matrix-synapse authlib && \
    dnf clean all

COPY /rootfs /

RUN id default || (groupadd -g 1001 default && useradd -d /opt/app-root/ -u 1001 -g 1001 default)

RUN PATHS=(/opt/app-root/src /etc/pki/ca-trust/extracted/ /etc/pki/ca-trust/source/anchors/) && \
    mkdir -p ${PATHS[@]} && \
    chmod -R 777 ${PATHS[@]} && \
    chown -R 1001:0 ${PATHS[@]} && \
    chmod -R g=u ${PATHS[@]}

USER 1001

WORKDIR ${HOME}

VOLUME /data/

# Synapse just always hits the 10s timeout and get's killed anyways, so let's be quick about it.
STOPSIGNAL SIGKILL

CMD ["/opt/app-root/src/init.sh"]

EXPOSE 8008/tcp
