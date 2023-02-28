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
    dnf -y --setopt tsflags= install kolab patch &&\
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

COPY /rootfs /

COPY kolab-init.sh /usr/local/sbin/
RUN chmod 750 /usr/local/sbin/kolab-init.sh

COPY kolab.conf /etc/kolab/kolab.conf
COPY cyrus.conf /etc/cyrus.conf
COPY imapd.conf /etc/imapd.conf
COPY imapd.annotations.conf /etc/imapd.annotations.conf
COPY guam.conf /etc/guam/sys.config

ARG DB_KOLAB_DATABASE
ARG DB_KOLAB_USERNAME
ARG DB_KOLAB_PASSWORD
ARG LDAP_HOST
ARG LDAP_ADMIN_BIND_DN
ARG LDAP_ADMIN_BIND_PW
ARG LDAP_SERVICE_BIND_PW
ARG IMAP_ADMIN_LOGIN
ARG IMAP_ADMIN_PASSWORD
RUN sed -i -r \
    -e "s|DB_KOLAB_DATABASE|$DB_KOLAB_DATABASE|g" \
    -e "s|DB_KOLAB_USERNAME|$DB_KOLAB_USERNAME|g" \
    -e "s|DB_KOLAB_PASSWORD|$DB_KOLAB_PASSWORD|g" \
    -e "s|LDAP_HOST|$LDAP_HOST|g" \
    -e "s|LDAP_ADMIN_BIND_DN|$LDAP_ADMIN_BIND_DN|g" \
    -e "s|LDAP_ADMIN_BIND_PW|$LDAP_ADMIN_BIND_PW|g" \
    -e "s|LDAP_SERVICE_BIND_PW|$LDAP_SERVICE_BIND_PW|g" \
    -e "s|IMAP_ADMIN_LOGIN|$IMAP_ADMIN_LOGIN|g" \
    -e "s|IMAP_ADMIN_PASSWORD|$IMAP_ADMIN_PASSWORD|g" \
    /etc/kolab/kolab.conf

RUN mkdir -p /imapdata/{spool,lib} && \
    rm -rf /var/spool/imap && ln -s /imapdata/spool /var/spool/imap && \
    mv /var/lib/imap /var/lib/imap-bak && ln -s /imapdata/lib /var/lib/imap && \
    chmod -R 777 /imapdata && \
    chown cyrus:mail /var/spool/imap /var/lib/imap

VOLUME [ "/sys/fs/cgroup" ]
VOLUME [ "/imapdata" ]

WORKDIR /root/

CMD ["/lib/systemd/systemd"]

EXPOSE 10143/tcp 10465/tcp 10587/tcp 11143/tcp 11993/tcp
