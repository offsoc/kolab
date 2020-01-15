#!/bin/bash

. ./settings.sh

sed -r -i \
    -e "s/^domain_base_dn.*$/domain_base_dn = ${domain_base_dn}/g" \
    -e '/^primary_mail/ a\
daemon_rcpt_policy = False' \
    -e '/^primary_mail/d' \
    -e '/secondary_mail/,+10d' \
    -e '/autocreate_folders/,+77d' \
    -e "/^\[kolab_wap\]/ a\
mgmt_root_dn = ${rootdn}" \
    -e "/^\[kolab_wap\]/ a\
hosted_root_dn = ${hosted_root_dn}" \
    -e "/^\[kolab_wap\]/ a\
api_url = http://127.0.0.1/kolab-webadmin/api" \
    -e 's/^auth_attributes.*$/auth_attributes = mail, uid/g' \
    /etc/kolab/kolab.conf

service kolabd restart
service kolab-saslauthd restart

