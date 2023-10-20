<?php

    function getenvlist($name) {
        $value = getenv($name);
        return $value == null ? null : explode(",", $value) ;
    }

    $config = array();

    $dbUsername = getenv('DB_RC_USERNAME');
    $dbPass = getenv('DB_RC_PASSWORD');
    $dbDatabase = getenv('DB_RC_DATABASE');
    $dbHost = getenv('DB_HOST');
    $config['db_dsnw'] = "mysql://$dbUsername:$dbPass@$dbHost/$dbDatabase";

    $config['fileapi_wopi_office'] = getenv('FILEAPI_WOPI_OFFICE');
    $config['fileapi_manticore'] = false;
    $config['fileapi_plugins'] = ['kolab_folders'];
    # Force https if we're behind a proxy. Browsers don't allow mixed content.
    $config['file_api_url'] = 'https://' . ($_SERVER['HTTP_HOST'] ?? null) . '/chwala/api/';

    $config['session_domain'] = '';
    $config['des_key'] = "wgtmEUSH9KOZC9rK8Qa5A6r4";
    $config['username_domain'] = 'kolab.org';
    $config['use_secure_urls'] = true;

    $config['mail_domain'] = '';

    // IMAP Server Settings
    $config['default_host'] = getenv('IMAP_HOST');
    $config['default_port'] = getenv('IMAP_PORT');
    $config['imap_delimiter'] = '/';
    $config['imap_force_lsub'] = true;
    // if (str_contains(getenv('IMAP_URI'), 'tls') || str_contains(getenv('IMAP_URI'), 'ssl')) {
    //     $config['imap_conn_options'] = [
    //         'ssl' => [
    //                 'verify_peer_name' => false,
    //                 'verify_peer' => false,
    //                 'allow_self_signed' => true
    //             ],
    //         'proxy_protocol' => getenv('IMAP_PROXY_PROTOCOL')
    //     ];
    // }
    $config['proxy_whitelist'] = getenvlist('PROXY_WHITELIST');

    // Caching and storage settings
    $config['imap_cache'] = 'db';
    $config['imap_cache_ttl'] = '10d';
    $config['messages_cache'] = 'db';
    $config['message_cache_ttl'] = '10d';
    $config['session_storage'] = 'db';

    // SMTP Server Settings
    $config['smtp_server'] = getenv('MAIL_HOST');
    $config['smtp_port'] = getenv('MAIL_PORT');
    $config['smtp_user'] = '%u';
    $config['smtp_pass'] = '%p';
    $config['smtp_helo_host'] = $_SERVER["HTTP_HOST"] ?? null;
    // $config['smtp_conn_options'] = Array(
    //     'ssl' => Array(
    //             'verify_peer_name' => false,
    //             'verify_peer' => false,
    //             'allow_self_signed' => true
    //         )
    // );

    // LDAP Settings
    $config['ldap_cache'] = 'db';
    $config['ldap_cache_ttl'] = '1h';

    // Kolab specific defaults
    $config['product_name'] = 'Kolab Groupware';
    $config['quota_zero_as_unlimited'] = false;
    $config['login_lc'] = 2;
    $config['auto_create_user'] = true;
    $config['enable_installer'] = false;
    // The SMTP server does not allow empty identities
    $config['mdn_use_from'] = true;

    // Plugins
    $config['plugins'] = array(
            // 'kolab_auth',
            'acl',
            'archive',
            'calendar',
            'jqueryui',
            'kolab_activesync',
            'kolab_addressbook',
            'kolab_config',
            //'kolab_delegation',
            'kolab_files',
            'kolab_folders',
            'kolab_notes',
            'kolab_tags',
            'managesieve',
            'newmail_notifier',
            'odfviewer',
            'password',
            'redundant_attachments',
            'tasklist',
            // contextmenu must be after kolab_addressbook (#444)
            'contextmenu',
'enigma',
        );


    // Do not show deleted messages, mark deleted messages as read,
    // and flag them as deleted instead of moving them to the Trash
    // folder.
    $config['skip_deleted'] = true;
    $config['read_when_deleted'] = true;
    $config['flag_for_deletion'] = true;
    $config['delete_always'] = true;

    $config['session_lifetime'] = 180;
    $config['password_charset'] = 'UTF-8';
    $config['useragent'] = 'Kolab 16/Roundcube ' . RCUBE_VERSION;

    $config['message_sort_col'] = 'date';

    $config['spellcheck_engine'] = 'pspell';
    $config['spellcheck_dictionary'] = true;
    $config['spellcheck_ignore_caps'] = true;
    $config['spellcheck_ignore_nums'] = true;
    $config['spellcheck_ignore_syms'] = true;
    $config['spellcheck_languages'] = array(
            'da' => 'Dansk',
            'de' => 'Deutsch',
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'it' => 'Italiano',
            'nl' => 'Nederlands',
            'pt' => 'Português',
            'ru' => 'Русский',
            'sv' => 'Svenska'
        );

    $config['undo_timeout'] = 10;
    $config['upload_progress'] = 2;
    $config['address_template'] = '{street}<br/>{locality} {zipcode}<br/>{country} {region}';
    $config['preview_pane'] = true;
    $config['preview_pane_mark_read'] = 0;

    $config['autoexpand_threads'] = 2;
    $config['top_posting'] = 0;
    $config['sig_above'] = false;
    $config['mdn_requests'] = 0;
    $config['mdn_default'] = false;
    $config['dsn_default'] = false;
    $config['reply_same_folder'] = false;

    if (file_exists(RCUBE_CONFIG_DIR . '/' . ($_SERVER["HTTP_HOST"] ?? null) . '/' . basename(__FILE__))) {
        include_once(RCUBE_CONFIG_DIR . '/' . ($_SERVER["HTTP_HOST"] ?? null) . '/' . basename(__FILE__));
    }

    // Re-apply mandatory settings here.

    $config['debug_level'] = 1;
    $config['devel_mode'] = false;
    $config['log_driver'] = 'stdout';
    $config['per_user_logging'] = true;
    $config['log_date_format'] = 'd-M-Y H:i:s,u O';
    $config['syslog_id'] = 'roundcube';
    $config['syslog_facility'] = LOG_USER;
    $config['smtp_log'] = false;
    $config['log_logins'] = true;
    $config['log_session'] = false;
    $config['sql_debug'] = false;
    $config['memcache_debug'] = false;
    $config['imap_debug'] = true;
    $config['ldap_debug'] = false;
    $config['smtp_debug'] = false;

    $config['skin'] = 'kolab';
    $config['skin_include_php'] = false;
    $config['mime_magic'] = null;
    $config['im_identify_path'] = '/usr/bin/identify';
    $config['im_convert_path'] = '/usr/bin/convert';
    $config['log_dir'] = 'logs/';
    #$config['temp_dir'] = '/var/lib/roundcubemail/';

    // Some additional default folders (archive plugin)
    $config['archive_mbox'] = 'Archive';
    // The Kolab daemon by default creates 'Spam'
    $config['junk_mbox'] = 'Spam';
    $config['default_folders'] = array('INBOX', 'Drafts', 'Sent', 'Spam', 'Trash', 'Archive');

    $config['address_book_type'] = 'ldap';
    $config['autocomplete_min_length'] = 3;
    $config['autocomplete_threads'] = 0;
    $config['autocomplete_max'] = 15;
    $config['ldap_public'] = array(
            'kolab_addressbook' => array(
                    'name'                      => 'Global Address Book',
                    'hosts'                     => Array(getenv('LDAP_HOST')),
                    'port'                      => 389,
                    'use_tls'                   => false,
                    'base_dn'                   => 'dc=hosted,dc=com',
                    'user_specific'             => true,
                    'bind_dn'                   => '%dn',
                    'bind_pass'                 => '',
                    'search_base_dn'            => 'dc=hosted,dc=com',
                    'search_bind_dn'            => 'uid=kolab-service,ou=Special Users,dc=mgmt,dc=com',
                    'search_bind_pw'            => getenv('LDAP_SERVICE_BIND_PW'),
                    'search_filter'             => '(&(objectClass=inetorgperson)(mail=%fu))',
                    'writable'                  => false,
                    'LDAP_Object_Classes'       => array("top", "inetorgperson"),
                    'required_fields'           => array("cn", "sn", "mail"),
                    'LDAP_rdn'                  => 'uid',
                    'ldap_version'              => 3,       // using LDAPv3
                    'search_fields'             => array('displayname', 'mail'),
                    'sort'                      => array('displayname', 'sn', 'givenname', 'cn'),
                    'scope'                     => 'sub',
                    'filter'                    => '(objectClass=inetorgperson)',
                    'vlv'                       => true,
                    'vlv_search'                => true,
                    'fuzzy_search'              => true,
                    'sizelimit'                 => '0',
                    'timelimit'                 => '0',
                    'fieldmap'                  => Array(
                            // Roundcube        => LDAP
                            'name'              => 'displayName',
                            'surname'           => 'sn',
                            'firstname'         => 'givenName',
                            'middlename'        => 'initials',
                            'email:primary'     => 'mail',
                            'email:alias'       => 'alias',
                            'email:personal'    => 'mailalternateaddress',
                            'phone:main'        => 'telephoneNumber',
                            'phone:work'        => 'alternateTelephoneNumber',
                            'phone:mobile'      => 'mobile',
                            'phone:work2'       => 'blackberry',
                            'jobtitle'          => 'title',
                            'manager'           => 'manager',
                            'assistant'         => 'secretary',
                            'photo'             => 'jpegphoto'
                        ),
                    'groups'                    => Array(
                            'base_dn'           => 'dc=hosted,dc=com',
                            'filter'            => '(&' . '(|(objectclass=groupofuniquenames)(objectclass=groupofurls))' . '(mail=*))',
                            'object_classes'    => Array("top", "groupOfUniqueNames"),
                            'member_attr'       => 'uniqueMember',
                        ),
                ),
        );

    $config['autocomplete_addressbooks'] = Array(
            'kolab_addressbook'
        );

    $config['autocomplete_single'] = true;

    $config['htmleditor'] = 0;

    $config['kolab_http_request'] = Array(
            'ssl_verify_host' => false,
            'ssl_verify_peer' => false,
        );

    @include('kolab_syncroton.inc.php');

?>
