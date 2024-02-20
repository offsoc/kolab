FROM apheleia/almalinux9

RUN dnf -y install ca-certificates gcc gcc-c++ cmake make bash \
    wget unzip systemd-devel wget flex bison \
    postgresql-libs postgresql-devel postgresql-server postgresql \
    cyrus-sasl-lib cyrus-sasl-devel openssl openssl-libs openssl-devel \
    libyaml-devel zlib-devel && \
    dnf clean all

WORKDIR /tmp/fluent-bit/
ENV VERSION=2.2.2
RUN wget https://github.com/fluent/fluent-bit/archive/refs/tags/v${VERSION}.tar.gz && tar xzf v${VERSION}.tar.gz && rm *.tar.gz
RUN  cd /tmp/fluent-bit/fluent-bit-${VERSION}/ && \
    cmake3 -DCMAKE_INSTALL_PREFIX="/opt/fluent-bit/" \
    -DCMAKE_INSTALL_SYSCONFDIR="/etc" \
    -DFLB_RELEASE="On" \
    -DFLB_TRACE="On" \
    -DFLB_SQLDB="On" \
    -DFLB_HTTP_SERVER="On" \
    -DFLB_OUT_KAFKA="On" \
    -DFLB_OUT_PGSQL="On" \
    -DFLB_JEMALLOC_OPTIONS="--with-lg-page=16 --with-lg-quantum=3" \
    -DFLB_JEMALLOC="On" \
    -DFLB_CHUNK_TRACE="On" \
    ./ && \
    make -j 4 && \
    make install && \
    cd / && \
    rm -rf  /tmp/fluent-bit && \
    chgrp -R 0 /opt/fluent-bit && \
    chmod -R g=u /opt/fluent-bit

COPY fluent-bit.yaml /etc/fluent-bit/fluent-bit.yaml 
COPY parsers.conf /etc/fluent-bit/parsers.conf 

RUN chgrp -R 0 /etc/fluent-bit && \
    chmod -R g=u /etc/fluent-bit
USER 1001
WORKDIR /
VOLUME /data

CMD [ "/opt/fluent-bit/bin/fluent-bit", "--config=/etc/fluent-bit/fluent-bit.yaml"]
