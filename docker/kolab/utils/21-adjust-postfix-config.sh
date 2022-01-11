#!/bin/bash

# new: (inetdomainstatus:1.2.840.113556.1.4.803:=1)
# active: (inetdomainstatus:1.2.840.113556.1.4.803:=2)
# suspended: (inetdomainstatus:1.2.840.113556.1.4.803:=4)
# deleted: (inetdomainstatus:1.2.840.113556.1.4.803:=8)
# confirmed: (inetdomainstatus:1.2.840.113556.1.4.803:=16)
# verified: (inetdomainstatus:1.2.840.113556.1.4.803:=32)
# ready: (inetdomainstatus:1.2.840.113556.1.4.803:=64)

sed -i -r \
    -e 's/^query_filter.*$/query_filter = (\&(associatedDomain=%s)(inetdomainstatus:1.2.840.113556.1.4.803:=18)(!(inetdomainstatus:1.2.840.113556.1.4.803:=4)))/g' \
    /etc/postfix/ldap/mydestination.cf

# new: (inetuserstatus:1.2.840.113556.1.4.803:=1)
# active: (inetuserstatus:1.2.840.113556.1.4.803:=2)
# suspended: (inetuserstatus:1.2.840.113556.1.4.803:=4)
# deleted: (inetuserstatus:1.2.840.113556.1.4.803:=8)
# ldapready: (inetuserstatus:1.2.840.113556.1.4.803:=16)
# imapready: (inetuserstatus:1.2.840.113556.1.4.803:=32)

sed -i -r \
    -e 's/^query_filter.*$/query_filter = (\&(|(mail=%s)(alias=%s))(|(objectclass=kolabinetorgperson)(|(objectclass=kolabgroupofuniquenames)(objectclass=kolabgroupofurls))(|(|(objectclass=groupofuniquenames)(objectclass=groupofurls))(objectclass=kolabsharedfolder))(objectclass=kolabsharedfolder))(!(inetuserstatus:1.2.840.113556.1.4.803:=4)))/g' \
    /etc/postfix/ldap/local_recipient_maps.cf

systemctl restart postfix
