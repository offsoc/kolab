FROM kolab/centos7:latest

RUN yum -y install rsyslog && \
    yum clean all

RUN systemctl disable \
        avahi-daemon.service \
        avahi-daemon.socket \
        sshd.service && \
    systemctl enable rsyslog

RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config

COPY kolab-init.service /etc/systemd/system/kolab-init.service
COPY kolab-init.sh /usr/local/sbin/
RUN chmod 750 /usr/local/sbin/kolab-init.sh

RUN rm -rf /etc/systemd/system/multi-user.target.wants/{avahi-daemon,sshd}.* && \
    ln -s /etc/systemd/system/kolab-init.service \
        /etc/systemd/system/multi-user.target.wants/kolab-init.service

CMD ["/lib/systemd/systemd"]

EXPOSE 21/tcp 22/tcp 25/tcp 53/tcp 53/udp 80/tcp 110/tcp 143/tcp 389/tcp 443/tcp 465/tcp 587/tcp 993/tcp 995/tcp 5353/udp 8880/tcp 8443/tcp 8447/tcp
