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
    dnf -y --setopt tsflags= install kolab-schema 389-ds-base &&\
    dnf clean all

COPY init.sh /init.sh
COPY kolab-init.service /etc/systemd/system/kolab-init.service
COPY kolab-setenv.service /etc/systemd/system/kolab-setenv.service

RUN systemctl disable avahi-daemon sshd; systemctl enable kolab-setenv kolab-init

RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config 2>/dev/null || :

RUN mkdir -p /ldapdata/{config,ssca,run} /var/run/dirsrv && \
    ln -s /ldapdata/config /etc/dirsrv/slapd-kolab && \
    ln -s /ldapdata/ssca /etc/dirsrv/ssca && \
    ln -s /ldapdata/run /var/run/dirsrv && \
    chmod -R 777 /ldapdata /etc/dirsrv

VOLUME [ "/sys/fs/cgroup" ]
VOLUME [ "/ldapdata" ]

WORKDIR /root/

CMD ["/lib/systemd/systemd"]

EXPOSE 389/tcp
