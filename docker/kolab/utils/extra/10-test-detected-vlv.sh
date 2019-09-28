#!/bin/bash

 . ./settings.sh

vlvbasedn=""
vlvscope=""
vlvfilter=""
vlvsort=""

(
    ldapsearch -o ldif-wrap=no -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -LLL -b "cn=ldbm database,cn=plugins,cn=config" "(objectclass=vlvsearch)" entrydn | sed -e '/^dn:/{
    $!{ N
    s/dn: //
    s/\n\s//
}};') | grep -v '^$' | while read vlvsearch; do

    vlvbasedn=`ldapsearch -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -LLL -b "${vlvsearch}" -s base vlvbase | sed '/^vlvbase:/{
    $!{ N
    s/vlvbase: //
    s/\n\s//
}}' | grep -vE "^(dn|\s)"`

    vlvscope=`ldapsearch -o ldif-wrap=no -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -LLL -b "${vlvsearch}" -s base vlvscope | sed '/^vlvscope:/{
    $!{ N
    s/vlvscope: //
    s/\n\s//
}}' | grep -vE "^(dn|\s)"`

    vlvfilter=`ldapsearch -o ldif-wrap=no -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -LLL -b "${vlvsearch}" -s base vlvfilter | sed '/^vlvfilter:/{
    $!{ N
    s/vlvfilter: //
    s/\n\s//
}}' | grep -vE "^(dn|\s)"`

    vlvsort=`ldapsearch -o ldif-wrap=no -x -h ${ldap_host} -D "${ldap_binddn}" -w "${ldap_bindpw}" -LLL -b "${vlvsearch}" -s sub "(objectclass=vlvIndex)" vlvsort | sed '/^vlvsort:/{
    $!{ N
    s/vlvsort: //
    s/\n\s//
}}' | grep -vE "^(dn|\s)"`

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
        -o ldif-wrap=no \
	-x \
        -h ${ldap_host} \
        -D "${ldap_binddn}" \
        -w "${ldap_bindpw}" \
        -b "${vlvsearch}" \
        -s sub \
        "(&(objectclass=vlvindex)(vlvsort=${vlvsort}))" \
        -LLL \
        vlvuses | \
        grep -i ^vlvuses | awk '{print $2}'`

    if [ -z "${uses_before}" ]; then
        uses_before=0
    fi

    echo "Searching '${vlvbasedn}'"

    echo "after" | ldapsearch \
        -o ldif-wrap=no \
	-x \
        -h ${ldap_host} \
        -D "${ldap_binddn}" \
        -w "${ldap_bindpw}" \
        -b "${vlvbasedn}" \
        -s ${vlvscope} "${vlvfilter}" \
        -E '!vlv=5/5/1/10' \
        -E "!sss=$(echo ${vlvsort} | sed -e 's| |/|g')" >/dev/null 2>&1

    echo "Searching '${vlvsearch}'"

    uses_after=`ldapsearch \
        -o ldif-wrap=no \
	-x \
        -h ${ldap_host} \
        -D "${ldap_binddn}" \
        -w "${ldap_bindpw}" \
        -b "${vlvsearch}" \
        -s sub \
        "(&(objectclass=vlvindex)(vlvsort=${vlvsort}))" \
        -LLL \
        vlvuses | \
        grep -i ^vlvuses | awk '{print $2}'`

    if [ -z "${uses_after}" ]; then
        uses_after=0
    fi

    if [ ${uses_before} -lt ${uses_after} ]; then
        echo "Actually works, too (before: ${uses_before}, after: ${uses_after})"
    else
        echo "Does not seem to work (uses before -eq after)"
        echo "Used: ldapsearch -x -h '${ldap_host}' -D '${ldap_binddn}' -w '${ldap_bindpw}' -b '${vlvbasedn}' -s ${vlvscope} '${vlvfilter}' -E '!vlv=5/5/1/10' -E '!sss=$(echo ${vlvsort} | sed -e 's| |/|g')'"
    fi
done


