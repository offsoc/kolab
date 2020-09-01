FROM ppc64le/centos:7

LABEL maintainer="contact@kolabsystems.com"
LABEL dist=centos7
LABEL tier=${TIER}


ENV container docker
MAINTAINER The CentOS Project <cloud-ops@centos.org>

RUN (cd /lib/systemd/system/sysinit.target.wants/; for i in *; do [ $i == systemd-tmpfiles-setup.service ] || rm -f $i; done); \
    rm -f /lib/systemd/system/multi-user.target.wants/*; \
    rm -f /etc/systemd/system/*.wants/*; \
    rm -f /lib/systemd/system/local-fs.target.wants/*; \
    rm -f /lib/systemd/system/sockets.target.wants/*udev*; \
    rm -f /lib/systemd/system/sockets.target.wants/*initctl*; \
    rm -f /lib/systemd/system/basic.target.wants/*; \
    rm -f /lib/systemd/system/anaconda.target.wants/*;

VOLUME [ "/sys/fs/cgroup" ]

ENV PLESK_DISABLE_HOSTNAME_CHECKING 1
ENV SYSTEMD_PAGER=''

ENV DISTRO=centos7

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

# Install troubleshooting utilities
RUN yum -y install \
        bind-utils \
        bzip2 \
        cmake \
        cyrus-imapd \
        cyrus-sasl-plain \
        expat-devel \
        firefox \
        gcc \
        git \
        lsof \
        mailx \
        net-tools \
        nmap-ncat \
        openldap-clients \
        openssh-server \
        psmisc \
        python36-devel \
        python36-pip \
        python36-pytz \
        strace \
        telnet \
        traceroute \
        vim-enhanced \
        wget \
        yum-plugin-priorities && \
    yum clean all

RUN git clone https://github.com/libwbxml/libwbxml.git && \
    cd libwbxml && \
    mkdir build && \
    cd build && \
    cmake -D CMAKE_INSTALL_PREFIX=/usr .. && \
    make && \
    make install && \
    cd .. && \
    rm -rf libwbxml

RUN adduser --uid $((9000 + $RANDOM % 1000)) avahi
RUN yum -y install avahi nss-mdns avahi-tools && yum clean all
RUN sed -i -e 's/hosts:.*/hosts:      mdns4_minimal [NOTFOUND=return] files dns myhostname/' /etc/nsswitch.conf

RUN wget -q -O /root/plesk-installer http://autoinstall.plesk.com/plesk-installer
RUN chmod +x /root/plesk-installer

# Install latest a more recent firefox version with headless support for selenium
RUN cd /usr/local/ && \
    wget -q https://mirror.kolabenterprise.com/pub/releases/firefox-latest.tar.bz2 && \
    tar xjf firefox-latest.tar.bz2 && \
    rm firefox-latest.tar.bz2

RUN ln -s /usr/local/firefox/firefox /usr/local/bin/firefox

# Selenium firefox driver
RUN cd /usr/local/bin && \
    wget -q https://mirror.kolabenterprise.com/pub/releases/geckodriver-latest.tar.gz && \
    tar xzf geckodriver-latest.tar.gz && \
    rm geckodriver-latest.tar.gz

RUN pip3.6 install distro pillow pytest pytest-html selenium xmltodict
RUN pip3.6 install \
    --global-option=build_ext \
    --global-option="-I/usr/include/libwbxml-1.0/wbxml/" \
    git+https://github.com/kanarip/python-wbxml#egg=wbxml

RUN rm -rvf /var/lock && mkdir -vp /var/lock/ && chmod -v 0777 /var/lock/

WORKDIR /root/

ENV PATH=/usr/lib/plesk-9.0:/usr/lib64/plesk-9.0:$PATH
ENV LANG=en_US.utf8
ENV LC_ALL=en_US.utf8

RUN yum -y install rsyslog && \
    yum --enablerepo=kolab-16-updates-testing -y update pykolab && \
    yum clean all

COPY kolab-init.service /etc/systemd/system/kolab-init.service
COPY kolab-vlv.service /etc/systemd/system/kolab-vlv.service
COPY utils /root/utils

RUN rm -rf /etc/systemd/system/multi-user.target.wants/{avahi-daemon,sshd}.* && \
    ln -s /etc/systemd/system/kolab-init.service \
        /etc/systemd/system/multi-user.target.wants/kolab-init.service && \
    ln -s /etc/systemd/system/kolab-vlv.service \
        /etc/systemd/system/multi-user.target.wants/kolab-vlv.service

RUN sed -i -r -e 's/^SELINUX=.*$/SELINUX=permissive/g' /etc/selinux/config 2>/dev/null || :

COPY kolab-init.sh /usr/local/sbin/
RUN chmod 750 /usr/local/sbin/kolab-init.sh
COPY kolab-vlv.sh /usr/local/sbin/
RUN chmod 750 /usr/local/sbin/kolab-vlv.sh

CMD ["/lib/systemd/systemd"]

EXPOSE 21/tcp 22/tcp 25/tcp 53/tcp 53/udp 80/tcp 110/tcp 143/tcp 389/tcp 443/tcp 465/tcp 587/tcp 993/tcp 995/tcp 5353/udp 8880/tcp 8443/tcp 8447/tcp
