<?php
return [
    'hosts' => explode(' ', env('LDAP_HOSTS', '127.0.0.1')),
    'port' => env('LDAP_PORT', 636),
    'use_tls' => (boolean)env('LDAP_USE_TLS', false),
    'use_ssl' => (boolean)env('LDAP_USE_SSL', true),

    'admin' => [
        'bind_dn' => env('LDAP_ADMIN_BIND_DN', null),
        'bind_pw' => env('LDAP_ADMIN_BIND_PW', null),
        'root_dn' => env('LDAP_ADMIN_ROOT_DN', null),
    ],

    'hosted' => [
        'bind_dn' => env('LDAP_HOSTED_BIND_DN', null),
        'bind_pw' => env('LDAP_HOSTED_BIND_PW', null),
        'root_dn' => env('LDAP_HOSTED_ROOT_DN', null),
    ],

    'domain_owner' => [
        // probably proxy credentials?
    ],

    'root_dn' => env('LDAP_ROOT_DN', null),
    'service_bind_dn' => env('LDAP_SERVICE_BIND_DN', null),
    'service_bind_pw' => env('LDAP_SERVICE_BIND_PW', null),
    'login_filter' => env('LDAP_LOGIN_FILTER', '(&(objectclass=kolabinetorgperson)(uid=%s))'),
    'filter' => env('LDAP_FILTER', '(&(objectclass=kolabinetorgperson)(uid=%s))'),
    'domain_name_attribute' => env('LDAP_DOMAIN_NAME_ATTRIBUTE', 'associateddomain'),
    'domain_base_dn' => env('LDAP_DOMAIN_BASE_DN', null),
    'domain_filter' => env('LDAP_DOMAIN_FILTER', '(associateddomain=%s)')
];
