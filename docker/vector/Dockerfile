FROM apheleia/almalinux9

RUN dnf -y install rust openssl-devel perl-FindBin protobuf-compiler git
RUN curl --proto '=https' --tlsv1.2 -sSfL https://sh.vector.dev -o vector.sh
RUN chmod +x ./vector.sh
RUN VECTOR_VERSION=0.40.0 ./vector.sh -y --prefix "/"

COPY rootfs /
RUN chgrp -R 0 /config && \
    chmod -R g=u /config

USER 1001
WORKDIR /
VOLUME /config
VOLUME /data

CMD ["vector", "--config", "/config/vector.yaml", "--config", "/config/transforms.yaml"]
