<?php

    // The id of the LDAP address book (which refers to the rcmail_config['ldap_public'])
    // or complete addressbook definition array.
    // Ends up being read by iRony even without the plugin configured, so must be empty
    $config['kolab_auth_addressbook'] = "";


    // This will overwrite defined filter
    $config['kolab_auth_filter'] = '(&' . '(objectclass=inetorgperson)' . '(|(uid=%u)(mail=%fu)(alias=%fu)))';

    // Use this fields (from fieldmap configuration) to get authentication ID
    $config['kolab_auth_login'] = 'email';

    // Use this fields (from fieldmap configuration) for default identity
    $config['kolab_auth_name']  = 'name';
    $config['kolab_auth_alias'] = 'alias';
    $config['kolab_auth_email'] = 'email';

    if (preg_match('/\/helpdesk-login\//', $_SERVER["REQUEST_URI"] ?? null) ) {

        // Login and password of the admin user. Enables "Login As" feature.
        $config['kolab_auth_admin_login']    = getenv('IMAP_ADMIN_LOGIN');
        $config['kolab_auth_admin_password'] = getenv('IMAP_ADMIN_PASSWORD');

        $config['kolab_auth_auditlog'] = true;
    }

    // Administrative role field (from fieldmap configuration) which must be filled with
    // specified value which adds privilege to login as another user.
    $config['kolab_auth_role']       = 'role';
    $config['kolab_auth_role_value'] = 'cn=kolab-admin,dc=mgmt,dc=com';

    // Administrative group name to which user must be assigned to
    // which adds privilege to login as another user.
    $config['kolab_auth_group'] = 'Kolab Helpdesk';

    if (file_exists(RCUBE_CONFIG_DIR . '/' . ($_SERVER["HTTP_HOST"] ?? null) . '/' . basename(__FILE__))) {
        include_once(RCUBE_CONFIG_DIR . '/' . ($_SERVER["HTTP_HOST"] ?? null) . '/' . basename(__FILE__));
    }

?>
