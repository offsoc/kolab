FROM almalinux:8

LABEL maintainer="contact@apheleia-it.ch"
LABEL dist=centos8
LABEL tier=${TIER}

ENV DISTRO=centos8
# ENV LANG=en_US.utf8
# ENV LC_ALL=en_US.utf8
# ENV APP_DOMAIN
# ENV LMTP_DESTINATION
# ENV DB_HOST
# ENV DB_USERNAME
# ENV DB_PASSWORD
# ENV DB_DATABASE

# Add EPEL.
RUN dnf -y install dnf-plugin-config-manager && \
    dnf config-manager --set-enabled powertools && \
    dnf -y install epel-release && \
    rpm --import /etc/pki/rpm-gpg/RPM-GPG-KEY-EPEL-8 && \
    dnf -y install \
    amavis \
    spamassassin \
    clamav \
    clamav-update \
    procps-ng \
    iputils \
    bind-utils \
    vim-enhanced && \
    dnf clean all

WORKDIR /root/

COPY /rootfs /

RUN mkdir -p /var/run/amavisd && \
    chmod -R 777 /var/run/amavisd && \
    mkdir -p /var/spool/amavisd && \
    chmod 777 /var/spool/amavisd

VOLUME [ "/var/spool/amavisd" ]

CMD ["/init.sh"]

EXPOSE 13024/tcp
