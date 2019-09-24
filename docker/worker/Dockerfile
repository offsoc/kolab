FROM kolab/centos7:latest

RUN yum -y install rsyslog && \
    yum clean all

RUN systemctl disable \
        avahi-daemon.service \
        avahi-daemon.socket \
        sshd.service && \
    systemctl enable rsyslog

RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config 2>/dev/null || :

RUN yum -y install kolab-16-extras-fasttrack && \
    sed -i -r -e '/^ssl/d' /etc/yum.repos.d/*.repo && \
    yum clean all

RUN yum -y update && \
    yum clean all

COPY kolab-worker.service /etc/systemd/system/kolab-worker.service
RUN systemctl enable kolab-worker.service

COPY kolab-worker.sh /usr/local/sbin/
RUN chmod 750 /usr/local/sbin/kolab-worker.sh

CMD ["/lib/systemd/systemd"]
