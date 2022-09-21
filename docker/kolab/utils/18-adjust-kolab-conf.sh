#!/bin/bash

. ./settings.sh

# sed -r -i \
#     -e "s/^base_dn.*$/base_dn = ${rootdn}/g" \
#     -e "s/^domain_base_dn.*$/domain_base_dn = ${domain_base_dn}/g" \
#     -e "s/^user_base_dn.*$/user_base_dn = ${hosted_domain_rootdn}/g" \
#     -e "s/^kolab_user_base_dn.*$/kolab_user_base_dn = ${hosted_domain_rootdn}/g" \
#     -e "s/^group_base_dn.*$/group_base_dn = ${hosted_domain_rootdn}/g" \
#     -e "s/^sharedfolder_base_dn.*$/sharedfolder_base_dn = ${hosted_domain_rootdn}/g" \
#     -e "s/^resource_base_dn.*$/resource_base_dn = ${hosted_domain_rootdn}/g" \
#     -e '/^primary_mail/ a\
# daemon_rcpt_policy = False' \
#     -e '/^primary_mail/d' \
#     -e '/secondary_mail/,+10d' \
#     -e '/autocreate_folders/,+77d' \
#     -e "/^\[kolab_wap\]/ a\
# mgmt_root_dn = ${rootdn}" \
#     -e "/^\[kolab_wap\]/ a\
# hosted_root_dn = ${hosted_domain_rootdn}" \
#     -e "/^\[kolab_wap\]/ a\
# api_url = http://127.0.0.1:9080/kolab-webadmin/api" \
#     -e 's/^auth_attributes.*$/auth_attributes = mail, uid/g' \
#     -e 's|^uri = imaps.*$|uri = imaps://127.0.0.1:11993|g' \
#     -e "/^\[wallace\]/ a\
# webmail_url = https://%(domain)s/roundcubemail" \
#     /etc/kolab/kolab.conf

systemctl restart kolabd
systemctl restart kolab-saslauthd
