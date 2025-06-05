<?php

    $config['kolab_freebusy_server'] = getenv('KOLAB_FREEBUSY_SERVER') ?: "https://" . ($_SERVER["HTTP_HOST"] ?? '') . "/freebusy/user/%u";

    if (file_exists(RCUBE_CONFIG_DIR . '/' . ($_SERVER["HTTP_HOST"] ?? null) . '/' . basename(__FILE__))) {
        include_once(RCUBE_CONFIG_DIR . '/' . ($_SERVER["HTTP_HOST"] ?? null) . '/' . basename(__FILE__));
    }

    $config['kolab_cache'] = true;

    $config['kolab_ssl_verify_host'] = false;
    $config['kolab_ssl_verify_peer'] = false;

    $config['kolab_use_subscriptions'] = true;

?>
