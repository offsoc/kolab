<?php

    $config['kolab_freebusy_server'] = '/freebusy';

    if (file_exists(RCUBE_CONFIG_DIR . '/' . ($_SERVER["HTTP_HOST"] ?? null) . '/' . basename(__FILE__))) {
        include_once(RCUBE_CONFIG_DIR . '/' . ($_SERVER["HTTP_HOST"] ?? null) . '/' . basename(__FILE__));
    }

    $config['kolab_cache'] = true;

    if (getenv('KOLABOBJECTS_COMPAT_MODE') != "true") {
        $config['kolab_dav_sharing'] = "sharing";
    }

    $config['kolab_ssl_verify_host'] = false;
    $config['kolab_ssl_verify_peer'] = false;

    $config['kolab_use_subscriptions'] = true;

?>
