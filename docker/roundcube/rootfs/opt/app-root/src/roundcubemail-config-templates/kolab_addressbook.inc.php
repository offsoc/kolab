<?php


// This option allows to set addressbooks priority or to disable some
// of them. Disabled addressbooks will be not shown in the UI. Default: 0.
// 0 - "Global address book(s) first". Use all address books, starting with the global (LDAP)
// 1 - "Personal address book(s) first". Use all address books, starting with the personal (Kolab)
// 2 - "Global address book(s) only". Use the global (LDAP) addressbook. Disable the personal.
// 3 - "Personal address book(s) only". Use the personal (Kolab) addressbook(s). Disable the global.
$config['kolab_addressbook_prio'] = 0;

// Base URL to build fully qualified URIs to access address books via CardDAV
// The following replacement variables are supported:
// %h - Current HTTP host
// %u - Current webmail user name
// %n - Folder name
// %i - Folder UUID
$config['kolab_addressbook_carddav_url'] = 'http://%h/dav/addressbooks/%u/%i';

?>
