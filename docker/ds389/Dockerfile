FROM centos/centos7:latest

MAINTAINER Liutauras Adomaitis <adomaitis@kolabsys.com>

RUN yum install -y epel-release 389-ds-base 389-adminutil gettext \
  && yum update -y \
  && yum clean all

COPY *.tpl ./
COPY kolab-schema.ldif 99kolab-schema.ldif

RUN for F in $(ls *.tpl); do eval "echo \"$(cat $F)\"" | tee $(basename $F .tpl); done

RUN rm -fr /var/lock /usr/lib/systemd/system \
  # No persistent storage
  # && mkdir /srv/dirsrv/{cnf,lib,log} && ln -s /srv/cnf/ /etc/dirsrv/ && ln -s /srv/lib/ /var/lib/dirsrv/ && ln -s /srv/log/ /var/log/dirsrv/ \
  && setup-ds.pl -ddd --silent --file /ds_setup.inf \
  && chown nobody.nobody -R /var/lib/dirsrv/

COPY *.ldif /var/lib/dirsrv/slapd-${DS_INSTANCE_NAME}/ldif/

EXPOSE 389

CMD for I in $(ls /var/lib/dirsrv/slapd-${DS_INSTANCE_NAME}/ldif/*-import.ldif || true); do \
    sed -r -i -e 's/mailHost: .*$/mailHost: localhost/g' ${I}; \
    chmod 644 ${I}; \
    /usr/lib64/dirsrv/slapd-${DS_INSTANCE_NAME}/ldif2db -Z ${DS_INSTANCE_NAME} -n ${I%%-import.ldif} -i ${I}; \
  done \
  && /usr/lib64/dirsrv/slapd-hkccp/start-slapd \
  && tail -F /var/log/dirsrv/slapd-$DS_INSTANCE_NAME/access /var/log/dirsrv/slapd-$DS_INSTANCE_NAME/errors
 
