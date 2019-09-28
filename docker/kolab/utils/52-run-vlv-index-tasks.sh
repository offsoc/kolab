#!/bin/bash

 . ./settings.sh

(
    echo "dn: cn=PVI,cn=index,cn=tasks,cn=config"
    echo "objectclass: top"
    echo "objectclass: extensibleObject"
    echo "cn: PVI"
    echo "nsinstance: ${hosted_domain_db}"
    echo "nsIndexVLVAttribute: PVI"
    echo ""
) | ldapmodify -a -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

ldap_complete=0

while [ ${ldap_complete} -ne 1 ]; do
    result=$(
            ldapsearch \
                -x \
                -h ${ldap_host} \
                -D "${ldap_binddn}" \
                -w "${ldap_bindpw}" \
                -c \
                -LLL \
                -b "cn=PVI,cn=index,cn=tasks,cn=config" \
                '(!(nstaskexitcode=0))' \
                -s base 2>/dev/null
        )
    if [ -z "$result" ]; then
        ldap_complete=1
        echo ""
    else
        echo -n "."
        sleep 1
    fi
done

(
    echo "dn: cn=RVI,cn=index,cn=tasks,cn=config"
    echo "objectclass: top"
    echo "objectclass: extensibleObject"
    echo "cn: RVI"
    echo "nsinstance: ${hosted_domain_db}"
    echo "nsIndexVLVAttribute: RVI"
    echo ""
) | ldapmodify -a -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

ldap_complete=0

while [ ${ldap_complete} -ne 1 ]; do
    result=$(
            ldapsearch \
                -x \
                -h ${ldap_host} \
                -D "${ldap_binddn}" \
                -w "${ldap_bindpw}" \
                -c \
                -LLL \
                -b "cn=RVI,cn=index,cn=tasks,cn=config" \
                '(!(nstaskexitcode=0))' \
                -s base 2>/dev/null
        )
    if [ -z "$result" ]; then
        ldap_complete=1
        echo ""
    else
        echo -n "."
        sleep 1
    fi
done



(
    echo "dn: cn=GVI,cn=index,cn=tasks,cn=config"
    echo "objectclass: top"
    echo "objectclass: extensibleObject"
    echo "cn: GVI"
    echo "nsinstance: ${hosted_domain_db}"
    echo "nsIndexVLVAttribute: GVI"
    echo ""
) | ldapmodify -a -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

ldap_complete=0

while [ ${ldap_complete} -ne 1 ]; do
    result=$(
            ldapsearch \
                -x \
                -h ${ldap_host} \
                -D "${ldap_binddn}" \
                -w "${ldap_bindpw}" \
                -c \
                -LLL \
                -b "cn=GVI,cn=index,cn=tasks,cn=config" \
                '(!(nstaskexitcode=0))' \
                -s base 2>/dev/null
        )
    if [ -z "$result" ]; then
        ldap_complete=1
        echo ""
    else
        echo -n "."
        sleep 1
    fi
done

if [ "${domain_base_dn}" != "cn=kolab,cn=config" ]; then
    (
        echo "dn: cn=DVI,cn=index,cn=tasks,cn=config"
        echo "objectclass: top"
        echo "objectclass: extensibleObject"
        echo "cn: DVI"
        echo "nsinstance: ${domain_db}"
        echo "nsIndexVLVAttribute: DVI"
        echo ""
    ) | ldapmodify -a -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

    ldap_complete=0

    while [ ${ldap_complete} -ne 1 ]; do
        result=$(
                ldapsearch \
                    -x \
                    -h ${ldap_host} \
                    -D "${ldap_binddn}" \
                    -w "${ldap_bindpw}" \
                    -c \
                    -LLL \
                    -b "cn=DVI,cn=index,cn=tasks,cn=config" \
                    '(!(nstaskexitcode=0))' \
                    -s base 2>/dev/null
            )
        if [ -z "$result" ]; then
            ldap_complete=1
            echo ""
        else
            echo -n "."
            sleep 1
        fi
    done
fi
