FROM quay.io/centos/centos:stream8

LABEL maintainer="contact@apheleia-it.ch"
LABEL dist=centos8
LABEL tier=${TIER}

ENV SYSTEMD_PAGER=''
ENV DISTRO=centos8
ENV LANG=en_US.utf8
ENV LC_ALL=en_US.utf8

# Add EPEL.
RUN dnf config-manager --set-enabled powertools && \
    dnf -y install \
        epel-release epel-next-release && \
    dnf -y module enable 389-directory-server:stable/default && \
    dnf -y module enable mariadb:10.3 && \
    dnf -y install iputils vim-enhanced bind-utils && \
    dnf clean all
RUN rpm --import /etc/pki/rpm-gpg/RPM-GPG-KEY-EPEL-8

# Install kolab
RUN rpm --import https://mirror.apheleia-it.ch/repos/Kolab:/16/key.asc && \
    rpm -Uvh https://mirror.apheleia-it.ch/repos/Kolab:/16/kolab-16-for-el8stream.rpm
RUN sed -i -e '/^ssl/d' /etc/yum.repos.d/kolab*.repo && \
    dnf config-manager --enable kolab-16-testing &&\
    dnf -y --setopt tsflags= install kolab dnsmasq patch &&\
    dnf clean all

COPY kolab-init.service /etc/systemd/system/kolab-init.service
COPY kolab-setenv.service /etc/systemd/system/kolab-setenv.service
COPY utils /root/utils

RUN rm -rf /etc/systemd/system/multi-user.target.wants/{avahi-daemon,sshd}.* && \
    ln -s /etc/systemd/system/kolab-init.service \
        /etc/systemd/system/multi-user.target.wants/kolab-init.service && \
    ln -s /etc/systemd/system/kolab-setenv.service \
        /etc/systemd/system/multi-user.target.wants/kolab-setenv.service


RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config 2>/dev/null || :
RUN sed -i -r -e 's/^Listen 80$/Listen 9080/g' /etc/httpd/conf/httpd.conf
#RUN sed -i -r -e 's/^Listen 443$/Listen 9443/g' /etc/httpd/conf/httpd.conf

COPY kolab-init.sh /usr/local/sbin/
RUN chmod 750 /usr/local/sbin/kolab-init.sh

COPY kolab.conf /etc/kolab/kolab.conf
COPY cyrus.conf /etc/cyrus.conf
COPY imapd.conf /etc/imapd.conf
COPY imapd.annotations.conf /etc/imapd.annotations.conf
COPY guam.conf /etc/guam/sys.config


RUN mkdir -p /imapdata/{spool,lib} && \
    rm -rf /var/spool/imap && ln -s /imapdata/spool /var/spool/imap && \
    mv /var/lib/imap /var/lib/imap-bak && ln -s /imapdata/lib /var/lib/imap && \
    chmod -R 777 /imapdata && \
    chown cyrus:mail /var/spool/imap /var/lib/imap

RUN mkdir -p /ldapdata/{config,ssca,run} /var/run/dirsrv && \
    ln -s /ldapdata/config /etc/dirsrv/slapd-kolab && \
    ln -s /ldapdata/ssca /etc/dirsrv/ssca && \
    ln -s /ldapdata/run /var/run/dirsrv && \
    chmod -R 777 /ldapdata /etc/dirsrv

VOLUME [ "/sys/fs/cgroup" ]
VOLUME [ "/imapdata" ]
VOLUME [ "/ldapdata" ]

WORKDIR /root/

CMD ["/lib/systemd/systemd"]

EXPOSE 21/tcp 22/tcp 25/tcp 53/tcp 53/udp 80/tcp 110/tcp 389/tcp 443/tcp 995/tcp 5353/udp 8880/tcp 8443/tcp 8447/tcp 10143/tcp 10465/tcp 10587/tcp 11143/tcp 11993/tcp
