#!/bin/bash

export rootdn=${LDAP_ADMIN_ROOT_DN:-"dc=mgmt,dc=com"}
export domain=${DOMAIN:-"mgmt.com"}
export domain_db=${DOMAIN_DB:-"mgmt_com"}
export ldap_host=${LDAP_HOST}
export ldap_binddn=${LDAP_ADMIN_BIND_DN:-"cn=Directory Manager"}
export ldap_bindpw=${LDAP_ADMIN_BIND_PW:-"Welcome2KolabSystems"}

export cyrus_admin=${IMAP_ADMIN_LOGIN:-"cyrus-admin"}

export imap_host=${IMAP_HOST}
export cyrus_admin_pw=${IMAP_ADMIN_PASSWORD:-"Welcome2KolabSystems"}

export kolab_service_pw=${LDAP_SERVICE_BIND_PW:-"Welcome2KolabSystems"}
export hosted_kolab_service_pw=${LDAP_HOSTED_BIND_PW:-"Welcome2KolabSystems"}

export hosted_domain=${HOSTED_DOMAIN:-"hosted.com"}
export hosted_domain_db=${HOSTED_DOMAIN_DB:-"hosted_com"}
export hosted_domain_rootdn=${LDAP_HOSTED_ROOT_DN:-"dc=hosted,dc=com"}

export domain_base_dn=${LDAP_DOMAIN_BASE_DN:-"ou=Domains,dc=mgmt,dc=com"}

export default_user_password=${DEFAULT_USER_PASSWORD:-"Welcome2KolabSystems"}
