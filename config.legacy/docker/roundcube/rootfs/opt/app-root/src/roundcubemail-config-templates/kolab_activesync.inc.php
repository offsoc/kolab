<?php

// The page with Activesync clients configuration manual
$config['activesync_setup_url'] = 'https://kb.kolabenterprise.com/documentation/setting-up-an-activesync-client';
// Force a subscription state per devicetype (lowercase) and folder
// States can be: 0 => not subscribed, 1 => subscribed, 2 => subscribed with alarm
$config['activesync_force_subscriptions'] = array('windowsoutlook15' => array('INBOX' => 1, 'Sent' => 1, 'Trash' => 1, 'Calendar' => 1, 'Contacts' => 1, 'Tasks' => 1));
