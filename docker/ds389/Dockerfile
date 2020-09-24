FROM centos/centos7:latest

MAINTAINER Liutauras Adomaitis <adomaitis@kolabsys.com>

RUN yum -y install \
        epel-release \
        389-ds-base \
        389-adminutil \
        gettext && \
    yum clean all

COPY *.tpl /
COPY kolab-schema.ldif /etc/dirsrv/schema/99kolab-schema.ldif

RUN for F in $(ls *.tpl); do eval "echo \"$(cat $F)\"" | tee ${F%%.tpl}; done

RUN rm -fr /var/lock /usr/lib/systemd/system && \
    setup-ds.pl -ddd --silent --file /ds_setup.inf && \
    chown nobody.nobody -R /var/lib/dirsrv/ && \
    mv mgmt_com-install.ldif ${DOMAIN_DB}-install.ldif && \
    mv hosted_com-install.ldif ${HOSTED_DOMAIN_DB}-install.ldif && \
    mv *.ldif /var/lib/dirsrv/slapd-${DS_INSTANCE_NAME}/ldif/.

# This only copies another kolab-schema.ldif
#COPY *.ldif /var/lib/dirsrv/slapd-${DS_INSTANCE_NAME}/ldif/

EXPOSE 389

CMD cd /var/lib/dirsrv/slapd-${DS_INSTANCE_NAME}/ldif/ && \
    for ldif in $(ls *-import.ldif || ls *-install.ldif || true); do \
        sed -r -i -e 's/mailHost: .*$/mailHost: localhost/g' ${ldif}; \
        chmod 644 ${ldif}; \
        namespace=$(echo "${ldif}" | sed -e 's/-import.ldif$//' -e 's/-install.ldif$//'); \
        /usr/lib64/dirsrv/slapd-${DS_INSTANCE_NAME}/ldif2db \
            -Z ${DS_INSTANCE_NAME} \
            -n ${namespace} \
            -i /var/lib/dirsrv/slapd-${DS_INSTANCE_NAME}/ldif/${ldif}; \
    done && \
  /usr/lib64/dirsrv/slapd-hkccp/start-slapd && \
  tail -F /var/log/dirsrv/slapd-${DS_INSTANCE_NAME}/{access,audit,errors}

