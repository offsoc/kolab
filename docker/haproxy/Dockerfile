FROM apheleia/almalinux9

RUN dnf -y install \
        --setopt 'tsflags=nodocs' \
        haproxy && \
    dnf clean all

COPY haproxy.cfg /etc/haproxy/haproxy.cfg

STOPSIGNAL SIGUSR1

CMD ["haproxy", "-d", "-db", "-W", "-f", "/etc/haproxy/haproxy.cfg"]

#EXPOSE 110/tcp 143/tcp 993/tcp 995/tcp
