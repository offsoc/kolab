FROM apheleia/swoole:latest

USER root

RUN dnf -y install findutils gnupg2 git rsync && \
    dnf clean all

EXPOSE 8000

ARG GIT_REF=master
ARG GIT_REMOTE=https://git.kolab.org/source/kolab.git
ARG CONFIG=config.prod
#Set this to prod or dev
ARG RELEASE_MODE=prod
COPY build.sh /build.sh
RUN /build.sh && \
    chgrp -R 0 /opt/app-root /tmp && \
    chmod -R g=u /opt/app-root /tmp && \
    chown -R 1001:0 /opt/app-root /tmp

COPY init.sh /init.sh
COPY update.sh /update.sh

USER 1001

CMD [ "/init.sh" ]
