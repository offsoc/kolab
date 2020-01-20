FROM kolab/centos7:latest

RUN yum -y install rsyslog && \
    yum clean all

COPY kolab-worker.service /etc/systemd/system/kolab-worker.service
RUN rm -rf /etc/systemd/system/multi-user.target.wants/{avahi-daemon,sshd}.* && \
    ln -s /etc/systemd/system/kolab-worker.service \
        /etc/systemd/system/multi-user.target.wants/kolab-worker.service

RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config 2>/dev/null || :

RUN yum -y install kolab-16-extras-fasttrack && \
    sed -i -r -e '/^ssl/d' /etc/yum.repos.d/*.repo && \
    yum -y update php\* pcre2\* && \
    yum clean all

RUN useradd worker
<<<<<<< HEAD
=======

>>>>>>> 19e8a716fefb7954fcfb100294ff35061b5acddd
COPY kolab-worker.sh /usr/local/bin/
RUN chmod 755 /usr/local/bin/kolab-worker.sh

CMD ["/lib/systemd/systemd"]
