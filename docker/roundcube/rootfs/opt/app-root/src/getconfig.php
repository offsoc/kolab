#!/usr/bin/php
<?php

const RCUBE_VERSION=1;
const RCUBE_CONFIG_DIR='roundcubemail/config/';
$_SERVER['HTTP_HOST'] = getenv('APP_DOMAIN');

@include('roundcubemail/config/config.inc.php');
@include('roundcubemail/config/calendar.inc.php');
@include('roundcubemail/config/tasklist.inc.php');
@include('roundcubemail/config/kolab_files.inc.php');
@include('roundcubemail/config/kolab_addressbook.inc.php');
@include('roundcubemail/config/chwala.inc.php');

print($config[$argv[1]]);

?>
