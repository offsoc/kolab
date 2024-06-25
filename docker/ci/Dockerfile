FROM quay.io/podman/stable

RUN dnf -y install git openssl

COPY init.sh /init.sh

ENV GIT_REF=master
ENV GIT_REMOTE=https://git.kolab.org/source/kolab.git

CMD [ "/init.sh" ]
