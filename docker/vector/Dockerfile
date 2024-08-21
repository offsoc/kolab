FROM apheleia/almalinux9

RUN dnf -y install openssl-devel perl-FindBin protobuf-compiler git make gcc perl cyrus-sasl-devel && \
    curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y --default-toolchain stable && \
    mkdir -p vector && \
    curl -sSfL --proto '=https' --tlsv1.2 https://api.github.com/repos/vectordotdev/vector/tarball/v0.40.0 | \
    tar xzf - -C vector --strip-components=1 && \
    pushd vector && \
    . "$HOME/.cargo/env" && \
    make build && \
    cp target/release/vector /usr/bin/vector && \
    popd && \
    rm -rf vector && \
    rm -rf $HOME/.cargo && \
    rm -rf $HOME/.rustup && \
    dnf remove -y openssl-devel perl-FindBin protobuf-compiler git make gcc perl cyrus-sasl-devel && \
    dnf clean all

COPY rootfs /
RUN chgrp -R 0 /config && \
    chmod -R g=u /config

USER 1001
WORKDIR /
VOLUME /config
VOLUME /data

CMD ["vector", "--config", "/config/vector.yaml", "--config", "/config/transforms.yaml"]
