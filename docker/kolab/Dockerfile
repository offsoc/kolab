FROM centos:7

LABEL maintainer="contact@kolabsystems.com"
LABEL dist=centos7
LABEL tier=${TIER}

ENV container docker
ENV SYSTEMD_PAGER=''
ENV DISTRO=centos7
ENV LANG=en_US.utf8
ENV LC_ALL=en_US.utf8

RUN (cd /lib/systemd/system/sysinit.target.wants/; for i in *; do [ $i == systemd-tmpfiles-setup.service ] || rm -f $i; done); \
    rm -f /lib/systemd/system/multi-user.target.wants/*; \
    rm -f /etc/systemd/system/*.wants/*; \
    rm -f /lib/systemd/system/local-fs.target.wants/*; \
    rm -f /lib/systemd/system/sockets.target.wants/*udev*; \
    rm -f /lib/systemd/system/sockets.target.wants/*initctl*; \
    rm -f /lib/systemd/system/basic.target.wants/*; \
    rm -f /lib/systemd/system/anaconda.target.wants/*;

# To speed things up, disable fastestmirror.
RUN sed -r -i \
        -e 's/^enabled.*$/enabled = 0/g' \
        /etc/yum/pluginconf.d/fastestmirror.conf

# Avoid using a mirrorlist (use a transparent proxy and cache everything instead).
RUN sed -r -i \
        -e 's/^mirrorlist/#mirrorlist/g' \
        -e 's/^#baseurl/baseurl/g' \
        /etc/yum.repos.d/*.repo

RUN sed -i -e '/tsflags=nodocs/d' /etc/yum.conf

# Add EPEL.
RUN yum -y install \
        epel-release && \
    yum clean all

# Add the EPEL key.
RUN rpm --import /etc/pki/rpm-gpg/RPM-GPG-KEY-EPEL-7

RUN rpm --import https://mirror.kolabenterprise.com/maipo.asc

RUN yum -y install https://mirror.kolabenterprise.com/kolab-16-for-el7.rpm && \
    yum -y install kolab-16-release-development patch && \
        yum clean all

RUN yum -y --setopt tsflags= install kolab roundcubemail-plugin-enigma
RUN mkdir /var/lib/roundcubemail/plugins/enigma && chmod 777 /var/lib/roundcubemail/plugins/enigma

COPY kolab-init.service /etc/systemd/system/kolab-init.service
COPY kolab-setenv.service /etc/systemd/system/kolab-setenv.service
COPY kolab-vlv.service /etc/systemd/system/kolab-vlv.service
COPY utils /root/utils

RUN rm -rf /etc/systemd/system/multi-user.target.wants/{avahi-daemon,sshd}.* && \
    ln -s /etc/systemd/system/kolab-init.service \
        /etc/systemd/system/multi-user.target.wants/kolab-init.service && \
    ln -s /etc/systemd/system/kolab-setenv.service \
        /etc/systemd/system/multi-user.target.wants/kolab-setenv.service && \
    ln -s /etc/systemd/system/kolab-vlv.service \
        /etc/systemd/system/multi-user.target.wants/kolab-vlv.service

RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config 2>/dev/null || :

RUN sed -i -r -e 's/^Listen 80$/Listen 9080/g' /etc/httpd/conf/httpd.conf
#RUN sed -i -r -e 's/^Listen 443$/Listen 9443/g' /etc/httpd/conf/httpd.conf

COPY kolab-init.sh /usr/local/sbin/
RUN chmod 750 /usr/local/sbin/kolab-init.sh
COPY kolab-vlv.sh /usr/local/sbin/
RUN chmod 750 /usr/local/sbin/kolab-vlv.sh

VOLUME [ "/sys/fs/cgroup" ]

WORKDIR /root/

CMD ["/lib/systemd/systemd"]

EXPOSE 21/tcp 22/tcp 25/tcp 53/tcp 53/udp 80/tcp 110/tcp 389/tcp 443/tcp 995/tcp 5353/udp 8880/tcp 8443/tcp 8447/tcp 10143/tcp 10465/tcp 10587/tcp 11143/tcp 11993/tcp
