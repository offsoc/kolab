<?php
    $config['kolab_folders_mail_inbox'] = 'INBOX';
    $config['kolab_folders_mail_drafts'] = 'Drafts';
    $config['kolab_folders_mail_sentitems'] = 'Sent';
    $config['kolab_folders_mail_junkemail'] = 'Spam';
    $config['kolab_folders_mail_outbox'] = '';
    $config['kolab_folders_mail_wastebasket'] = 'Trash';

    if (file_exists(RCUBE_CONFIG_DIR . '/' . $_SERVER["HTTP_HOST"] . '/' . basename(__FILE__))) {
        include_once(RCUBE_CONFIG_DIR . '/' . $_SERVER["HTTP_HOST"] . '/' . basename(__FILE__));
    }

?>
