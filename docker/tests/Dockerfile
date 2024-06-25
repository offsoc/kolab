FROM kolab-webapp:latest

USER root

RUN dnf -y install findutils chromium php-xdebug
RUN cd /opt/app-root/src/ && ./artisan dusk:chrome-driver $(rpmquery --queryformat="%{VERSION}" chromium | awk -F'.' '{print $1}')

COPY init.sh /init.sh
