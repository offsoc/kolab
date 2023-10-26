#!/bin/bash

 . ./settings.sh

ldap_binddn=$(ldapsearch -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -LLL -b "${hosted_domain_rootdn}" "(mail=jdoe@example.org)" entrydn | grep ^dn | cut -d':' -f2-)
ldap_bindpw="simple123"

export ldap_binddn
export ldap_bindpw

(
    ldapsearch -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -LLL -b "cn=ldbm database,cn=plugins,cn=config" "(objectclass=vlvsearch)" entrydn | grep ^dn | cut -d':' -f2-
) | while read vlvsearch; do
    vlvbasedn=`ldapsearch -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -LLL -b "${vlvsearch}" -s base vlvbase | grep -i ^vlvbase | awk 'BEGIN { FS = ": " } ; {print $2}'`
    vlvscope=`ldapsearch -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -LLL -b "${vlvsearch}" -s base vlvscope | grep -i ^vlvscope | awk 'BEGIN { FS = ": " } ; {print $2}'`
    vlvfilter=`ldapsearch -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -LLL -b "${vlvsearch}" -s base vlvfilter | grep -i ^vlvfilter | awk 'BEGIN { FS = ": " } ; {print $2}'`
    vlvsort=`ldapsearch -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -LLL -b "${vlvsearch}" -s sub "(objectclass=vlvIndex)" vlvsort | grep -i ^vlvsort | awk 'BEGIN { FS = ": " } ; {print $2}'`

    echo "Found a VLV index and search with parameters:"
    echo " - Base: ${vlvbasedn}"
    echo -n " - Scope: "

    case ${vlvscope} in
        0)
            echo "base"
            vlvscope="base"
        ;;

        1)
            echo "one"
            vlvscope="one"
        ;;

        2)
            echo "sub"
            vlvscope="sub"
        ;;
    esac

    echo " - Filter: ${vlvfilter}"
    echo " - Sorting by: ${vlvsort}"

    # Use it

    uses_before=`ldapsearch \
        -x \
        -h ${ldap_host} \
        -D "${ldap_binddn}" \
        -w "${ldap_bindpw}" \
        -b "cn=ldbm database,cn=plugins,cn=config" \
        -s sub \
        "(&(objectclass=vlvindex)(vlvsort=${vlvsort}))" \
        -LLL \
        vlvuses | \
        grep -i ^vlvuses | awk '{print $2}'`

    echo "after" | ldapsearch \
        -x \
        -h ${ldap_host} \
        -D "${ldap_binddn}" \
        -w "${ldap_bindpw}" \
        -LLL \
        -b "${vlvbasedn}" \
        -s ${vlvscope} "(&${vlvfilter}(|(mail=*xqg*)(displayname=*xqg*)(alias=*xqg*)))" \
        -E '!vlv=5/5/1/10' \
        -E "!sss=$(echo ${vlvsort} | sed -e 's| |/|g')" \
        mail >/dev/null 2>&1

    retval=$?

    if [ $retval -eq 0 ]; then
        echo "ldapsearch command completed successfully:"
    else
        echo "Return value is $retval"
    fi

    echo "ldapsearch -x -h ${ldap_host} -D \"${ldap_binddn}\" -w \"${ldap_bindpw}\" -b \"${vlvbasedn}\" -LLL \\"
    echo "    -s ${vlvscope} \"(&${vlvfilter}(|(mail=*xqg*)(displayname=*xqg*)(alias=*xqg*)))\" \\"
    echo "    -E '!vlv=5/5/1/10' -E '!sss=$(echo ${vlvsort} | sed -e 's| |/|g')' mail"

    uses_after=`ldapsearch \
        -x \
        -h ${ldap_host} \
        -D "${ldap_binddn}" \
        -w "${ldap_bindpw}" \
        -b "cn=ldbm database,cn=plugins,cn=config" \
        -s sub \
        "(&(objectclass=vlvindex)(vlvsort=${vlvsort}))" \
        -LLL \
        vlvuses | \
        grep -i ^vlvuses | awk '{print $2}'`

    if [ ${uses_before} -lt ${uses_after} ]; then
        echo "Actually works, too (before: ${uses_before}, after: ${uses_after})"
    fi
done


