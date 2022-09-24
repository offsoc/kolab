FROM fedora:35

RUN dnf -y install --setopt=install_weak_deps=False \
        389-ds-base python3-lib389 && \
    dnf -y clean all

RUN mkdir -p /data/{config,ssca,run} /var/run/dirsrv && \
    ln -s /data/config /etc/dirsrv/slapd-localhost && \
    ln -s /data/ssca /etc/dirsrv/ssca && \
    ln -s /data/run /var/run/dirsrv && \
    chmod -R 777 /data /etc/dirsrv

HEALTHCHECK --start-period=5m --timeout=5s --interval=5s --retries=2 \
    CMD /usr/lib/dirsrv/dscontainer -H

VOLUME /data

# ADD init.sh /init.sh
# CMD ["/init.sh"]

CMD ["/usr/libexec/dirsrv/dscontainer", "-r"]

EXPOSE 3389 3636
