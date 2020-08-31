FROM centos/centos7:latest

MAINTAINER Liutauras Adomaitis <adomaitis@kolabsys.com>

RUN yum install -y epel-release 389-ds-base 389-adminutil gettext \
  && yum update -y \
  && yum clean all

  COPY *.tpl ./
  COPY kolab-schema.ldif 99kolab-schema.ldif

  RUN for F in $(ls *.tpl); do eval "echo \"$(cat $F)\"" | tee $(basename $F .tpl); done

  RUN useradd ldapadmin \
    && rm -fr /var/lock /usr/lib/systemd/system \
    # No persistent storage 
    # && mkdir /srv/dirsrv/{cnf,lib,log} && ln -s /srv/cnf/ /etc/dirsrv/ && ln -s /srv/lib/ /var/lib/dirsrv/ && ln -s /srv/log/ /var/log/dirsrv/ \
    && setup-ds.pl -ddd --silent --file /ds_setup.inf \
    && chown nobody.nobody -R /var/lib/dirsrv/

  EXPOSE 389

  CMD for B in $(ls /tmp/ds389-load/*_backend.ldif); do \
        ## eval "echo \"$(cat $B)\"" | tee $(basename $B .tpl); \
        /usr/lib64/dirsrv/slapd-${DS_INSTANCE_NAME}/ldif2db -Z ${DS_INSTANCE_NAME} -n $(basename ${B} _backend.ldif) -i ${B}; \
    done \
    && /usr/lib64/dirsrv/slapd-hkccp/start-slapd \
    && tail -F /var/log/dirsrv/slapd-$DS_INSTANCE_NAME/access /var/log/dirsrv/slapd-$DS_INSTANCE_NAME/errors
 
