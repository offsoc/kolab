FROM kolab-webapp:latest

USER root

RUN dnf -y install \
        --setopt=install_weak_deps=False \
        --setopt 'tsflags=nodocs' \
        chromium php-xdebug && \
    dnf clean all
RUN cd /opt/app-root/src/ && ./artisan dusk:chrome-driver $(rpmquery --queryformat="%{VERSION}" chromium | awk -F'.' '{print $1}')

COPY init.sh /init.sh
