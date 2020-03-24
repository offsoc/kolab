FROM fedora:31

RUN dnf -y install \
        nginx \
        nginx-mod-mail \
        rsyslog && \
    dnf clean all

RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config 2>/dev/null || :

COPY nginx.conf /etc/nginx/nginx.conf

#RUN /usr/sbin/nginx -t

RUN ln -s /usr/lib/systemd/system/nginx.service \
    /etc/systemd/system/multi-user.target.wants/nginx.service

CMD ["/lib/systemd/systemd"]

EXPOSE 110/tcp 143/tcp 993/tcp 995/tcp
