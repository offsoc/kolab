<?php

if (getenv('KOLABOBJECTS_COMPAT_MODE') == "true") {
    $config['tasklist_driver'] = 'kolab';
} else {
    $config['tasklist_driver'] = 'caldav';
    $config['tasklist_caldav_server'] = getenv('TASKLIST_CALDAV_SERVER') ?: "https://" . ($_SERVER["HTTP_HOST"] ?? null) . "/dav";
}

// default sorting order of tasks listing (auto, datetime, startdatetime, flagged, complete, changed)
$config['tasklist_sort_col'] = '';

// default sorting order for tasks listing (asc or desc)
$config['tasklist_sort_order'] = 'asc';

