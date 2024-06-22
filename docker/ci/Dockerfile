FROM quay.io/podman/stable

RUN dnf -y install git openssl

COPY init.sh /init.sh

CMD [ "/init.sh" ]
