FROM apheleia/almalinux8

RUN dnf -y install \
    python3 \
    python3-requests \
    postfix \
    postfix-mysql \
    cyrus-sasl \
    cyrus-sasl-plain && \
    dnf clean all

WORKDIR /root/

COPY /rootfs /

VOLUME [ "/var/spool/postfix" ]
VOLUME [ "/var/lib/postfix" ]

ENV SERVICES_PORT=8000
# ENV APP_SERVICES_DOMAIN
# ENV APP_DOMAIN
# ENV LMTP_DESTINATION
ENV MYNETWORKS="172.0.0.0/8,127.0.0.0/8"
ENV AMAVIS_HOST="amavis"
# ENV DB_HOST
# ENV DB_USERNAME
# ENV DB_PASSWORD
# ENV DB_DATABASE

CMD ["/init.sh"]

EXPOSE 10025/tcp 10587/tcp 11465/tcp 11587/tcp
