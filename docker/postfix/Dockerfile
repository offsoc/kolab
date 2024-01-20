FROM almalinux:8

LABEL maintainer="contact@apheleia-it.ch"
LABEL dist=centos8

# Add EPEL.
RUN dnf -y install dnf-plugin-config-manager && \
    dnf config-manager --set-enabled powertools && \
    dnf -y install \
    epel-release \
    python3 \
    python3-requests \
    postfix \
    postfix-mysql \
    cyrus-sasl \
    cyrus-sasl-plain \
    procps-ng \
    iputils \
    bind-utils \
    tcpdump \
    vim-enhanced && \
    dnf clean all
RUN rpm --import /etc/pki/rpm-gpg/RPM-GPG-KEY-EPEL-8

RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config 2>/dev/null || :

WORKDIR /root/

COPY /rootfs /

VOLUME [ "/var/spool/postfix" ]
VOLUME [ "/var/lib/postfix" ]

ENV SERVICES_PORT=8000
# ENV APP_SERVICES_DOMAIN
# ENV APP_DOMAIN
# ENV LMTP_DESTINATION
ENV MYNETWORKS="172.0.0.0/8"
# ENV DB_HOST
# ENV DB_USERNAME
# ENV DB_PASSWORD
# ENV DB_DATABASE

CMD ["/init.sh"]

EXPOSE 10025/tcp 10587/tcp 11465/tcp 11587/tcp
