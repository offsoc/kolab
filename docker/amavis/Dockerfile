FROM apheleia/almalinux9

# ENV LANG=en_US.utf8
# ENV LC_ALL=en_US.utf8
# ENV APP_DOMAIN
# ENV LMTP_DESTINATION
# ENV DB_HOST
# ENV DB_USERNAME
# ENV DB_PASSWORD
# ENV DB_DATABASE

RUN dnf -y install \
    amavis \
    spamassassin \
    clamav \
    clamav-update && \
    dnf clean all

WORKDIR /root/

COPY /rootfs /

RUN mkdir -p /var/run/amavisd /var/dkim && \
    chmod -R 777 /var/run/amavisd /var/dkim && \
    mkdir -p /var/spool/amavisd && \
    chmod 777 /var/spool/amavisd

VOLUME [ "/var/spool/amavisd" ]
VOLUME [ "/var/lib/spamassassin" ]
VOLUME [ "/var/lib/clamav" ]
VOLUME [ "/var/dkim" ]

ENV POSTFIX_HOST=postfix
ENV DKIM_IDENTIFIER=dkim20240318
ENV DEBUG=false
ENV CLAMD=false

CMD ["/init.sh"]

EXPOSE 13024/tcp
