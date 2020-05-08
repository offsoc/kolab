FROM kolab/centos7:latest

RUN yum -y install rsyslog && \
    yum --enablerepo=kolab-16-updates-testing -y update pykolab && \
    yum clean all

COPY kolab-init.service /etc/systemd/system/kolab-init.service
COPY kolab-vlv.service /etc/systemd/system/kolab-vlv.service

RUN rm -rf /etc/systemd/system/multi-user.target.wants/{avahi-daemon,sshd}.* && \
    ln -s /etc/systemd/system/kolab-init.service \
        /etc/systemd/system/multi-user.target.wants/kolab-init.service && \
    ln -s /etc/systemd/system/kolab-vlv.service \
        /etc/systemd/system/multi-user.target.wants/kolab-vlv.service

RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config 2>/dev/null || :

RUN sed -i -r -e 's/^Listen 80$/Listen 9080/g' /etc/httpd/conf/httpd.conf
#RUN sed -i -r -e 's/^Listen 443$/Listen 9443/g' /etc/httpd/conf/httpd.conf

COPY kolab-init.sh /usr/local/sbin/
RUN chmod 750 /usr/local/sbin/kolab-init.sh
COPY kolab-vlv.sh /usr/local/sbin/
RUN chmod 750 /usr/local/sbin/kolab-vlv.sh

CMD ["/lib/systemd/systemd"]

EXPOSE 21/tcp 22/tcp 25/tcp 53/tcp 53/udp 80/tcp 110/tcp 143/tcp 389/tcp 443/tcp 465/tcp 587/tcp 993/tcp 995/tcp 5353/udp 8880/tcp 8443/tcp 8447/tcp
