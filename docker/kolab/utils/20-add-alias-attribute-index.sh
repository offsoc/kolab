#!/bin/bash

 . ./settings.sh

export index_attr=alias

(
    echo "dn: cn=${index_attr},cn=index,cn=${hosted_domain_db},cn=ldbm database,cn=plugins,cn=config"
    echo "objectclass: top"
    echo "objectclass: nsindex"
    echo "cn: ${index_attr}"
    echo "nsSystemIndex: false"
    echo "nsindextype: pres"
    echo "nsindextype: eq"
    echo "nsindextype: sub"

) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c


(
    echo "dn: cn=${hosted_domain_db} ${index_attr} index,cn=index,cn=tasks,cn=config"
    echo "objectclass: top"
    echo "objectclass: extensibleObject"
    echo "cn: ${hosted_domain_db} ${index_attr} index"
    echo "nsinstance: ${hosted_domain_db}"
    echo "nsIndexAttribute: ${index_attr}:pres"
    echo "nsIndexAttribute: ${index_attr}:eq"
    echo "nsIndexAttribute: ${index_attr}:sub"
    echo ""
) | ldapadd -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -c

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
                -b "cn=${hosted_domain_db} ${index_attr} index,cn=index,cn=tasks,cn=config" \
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

