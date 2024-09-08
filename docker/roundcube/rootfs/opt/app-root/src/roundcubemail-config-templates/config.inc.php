<?php

//This check is for some reason required under phpunit
if (!function_exists("getenvlist")) {
    function getenvlist($name) {
        $value = getenv($name);
        return $value == null ? null : explode(",", $value) ;
    }
}

    $config = array();
    $dbUsername = getenv('DB_RC_USERNAME');
    $dbPass = getenv('DB_RC_PASSWORD');
    $dbDatabase = getenv('DB_RC_DATABASE');
    $dbHost = getenv('DB_HOST');
    $config['db_dsnw'] = "mysql://$dbUsername:$dbPass@$dbHost/$dbDatabase";

    $components = explode('.', $_SERVER["HTTP_HOST"] ?? "");
    if (count($components) > 2) {
        array_shift($components);
    }
    $config['session_domain'] = implode('.', $components);

    $config['des_key'] = getenv('DES_KEY');
    $config['username_domain'] = getenv('APP_DOMAIN');
    $config['use_secure_urls'] = true;

    $config['mail_domain'] = '';

    // IMAP Server Settings
    $config['default_host'] = (getenv('IMAP_TLS') == "true" ? "ssl://" : "") . getenv('IMAP_HOST');
    $config['default_port'] = getenv('IMAP_PORT');
    $config['imap_delimiter'] = '/';
    $config['imap_force_lsub'] = true;
    if (getenv('IMAP_TLS') == "true") {
        $config['imap_conn_options'] = [
            'ssl' => [
                    'verify_peer_name' => false,
                    'verify_peer' => false,
                    'allow_self_signed' => true
                ],
            'proxy_protocol' => getenv('IMAP_PROXY_PROTOCOL')
        ];
    }
    $config['proxy_whitelist'] = getenvlist('PROXY_WHITELIST');

    // Caching and storage settings
    $config['imap_cache'] = 'redis';
    $config['imap_cache_ttl'] = '10d';
    $config['messages_cache'] = 'db'; // no redis available
    $config['message_cache_ttl'] = '10d';
    $config['session_storage'] = 'redis';
    $config['redis_hosts'] = [getenv('REDIS_HOST') . ':6379:3:' . getenv('REDIS_PASSWORD')];

    // SMTP Server Settings
    if (getenv('SUBMISSION_ENCRYPTION') == "starttls") {
        $config['smtp_server'] = "tls://" . getenv('SUBMISSION_HOST');
    } else {
        $config['smtp_server'] = getenv('SUBMISSION_HOST');
    }
    $config['smtp_port'] = getenv('SUBMISSION_PORT');
    $config['smtp_user'] = '%u';
    $config['smtp_pass'] = '%p';
    $config['smtp_helo_host'] = $_SERVER["HTTP_HOST"] ?? null;
    if (!empty(getenv('SUBMISSION_ENCRYPTION'))) {
        $config['smtp_conn_options'] = [
            'ssl' => [
                'verify_peer_name' => false,
                'verify_peer' => false,
                'allow_self_signed' => true
            ]
        ];
    }

    // Kolab specific defaults
    $config['product_name'] = 'Kolab Groupware';
    $config['quota_zero_as_unlimited'] = false;
    $config['login_lc'] = 2;
    $config['auto_create_user'] = true;
    $config['enable_installer'] = false;
    // The SMTP server does not allow empty identities
    $config['mdn_use_from'] = true;

    // Plugins
    $plugins = [
        'acl',
        'archive',
        'calendar',
        'jqueryui',
        'kolab_activesync',
        'kolab_addressbook',
        'kolab_files',
        'managesieve',
        'newmail_notifier',
        'odfviewer',
        'redundant_attachments',
        'contextmenu',
        'tasklist',
        'enigma',
    ];

    if (getenv('KOLABOBJECTS_COMPAT_MODE') == "true") {
        $plugins[] = 'kolab_config';
        $plugins[] = 'kolab_folders';
        $plugins[] = 'kolab_notes';
        $plugins[] = 'kolab_tags';

        // These require ldap
        // $plugins[] = 'kolab_auth';
        // $plugins[] = 'kolab_delegation';
    }

    if ($disabledPlugins = getenvlist('DISABLED_PLUGINS')) {
        $plugins = array_diff($plugins, $disabledPlugins);
    }
    if ($extraPlugins = getenvlist('EXTRA_PLUGINS')) {
        $plugins = array_merge($plugins, $extraPlugins);
    }

    // contextmenu must be after kolab_addressbook (#444)
    $plugins[] = 'contextmenu';

    $config['plugins'] = $plugins;

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
    $config['log_driver'] = getenv('LOG_DRIVER');
    $config['per_user_logging'] = true;
    $config['log_date_format'] = 'd-M-Y H:i:s,u O';
    $config['smtp_log'] = false;
    $config['log_logins'] = true;
    $config['log_session'] = false;
    $config['sql_debug'] = getenv('SQL_DEBUG');
    $config['memcache_debug'] = getenv('MEMCACHE_DEBUG');
    $config['imap_debug'] = getenv('IMAP_DEBUG');
    $config['smtp_debug'] = getenv('SMTP_DEBUG');
    $config['dav_debug'] = getenv('DAV_DEBUG');

    $config['skin'] = getenv('SKIN');
    $config['skin_include_php'] = false;
    $config['mime_magic'] = null;
    $config['im_identify_path'] = '/usr/bin/identify';
    $config['im_convert_path'] = '/usr/bin/convert';
    $config['log_dir'] = 'logs/';
    #$config['temp_dir'] = '/var/lib/roundcubemail/';

    $config['create_default_folders'] = true;
    // Some additional default folders (archive plugin)
    $config['archive_mbox'] = 'Archive';
    // The Kolab daemon by default creates 'Spam'
    $config['junk_mbox'] = 'Spam';

    // $config['address_book_type'] = 'ldap';
    $config['autocomplete_min_length'] = 3;
    $config['autocomplete_threads'] = 0;
    $config['autocomplete_max'] = 15;

    // Disable the default addressbook and use the dav addressbook by default
    $config['address_book_type'] = '';

    $config['autocomplete_single'] = true;

    $config['htmleditor'] = 0;

    $config['kolab_http_request'] = Array(
            'ssl_verify_host' => false,
            'ssl_verify_peer' => false,
        );

    @include('kolab_syncroton.inc.php');
    @include('chwala.inc.php');

?>
