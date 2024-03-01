FROM apheleia/almalinux8

WORKDIR /root/

RUN dnf -y group install "Development Tools" && \
    dnf -y install git autoconf automake bison cyrus-sasl-devel flex gcc gperf jansson-devel libbsd-devel libtool libicu-devel libuuid-devel openssl-devel pkgconfig sqlite-devel brotli-devel libical-devel libxml2-devel libnghttp2-devel shapelib  zlib-devel pcre-devel perl-devel cyrus-imapd cyrus-sasl cyrus-sasl-plain && \
    dnf clean all

ARG GIT_REF=dev/mollekopf
ARG GIT_REMOTE=https://git.kolab.org/source/cyrus-imapd

ADD build.sh /build.sh
RUN /build.sh

COPY cyrus.conf /etc/cyrus.conf
COPY imapd.conf /etc/imapd.conf
COPY imapd.annotations.conf /etc/imapd.annotations.conf
COPY saslauthd.conf /etc/saslauthd.conf

ADD init.sh /init.sh

VOLUME [ "/var/spool/imap" ]
VOLUME [ "/var/lib/imap" ]

RUN id default || (groupadd -g 1001 default && useradd -u 1001 -g 1001 default)

RUN PATHS=(/run /run/saslauthd /var/run /var/lib/imap /var/spool/imap /etc/pki/cyrus-imapd) && \
    mkdir -p ${PATHS[@]} && \
    chmod 777 ${PATHS[@]} && \
    chown -R 1001:0 ${PATHS[@]} && \
    chmod -R g=u ${PATHS[@]}

RUN PATHS=(/etc /etc/passwd /etc/saslauthd.conf /etc/cyrus.conf /etc/imapd.conf) && \
    chown 1001:0 ${PATHS[@]} && \
    chmod g=u ${PATHS[@]}

USER 1001

ENV SERVICES_PORT=8000
# ENV APP_SERVICES_DOMAIN

CMD ["/init.sh"]

EXPOSE 11143/tcp 11993/tcp 11080/tcp 11443/tcp 11024/tcp 4190/tcp
