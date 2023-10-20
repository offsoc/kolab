<?php

// URL of kolab-chwala installation
$config['kolab_files_url'] = 'https://' . ($_SERVER["HTTP_HOST"] ?? null) . '/chwala/';
// This is how the plugin does chwala api requests on the server
$config['kolab_files_server_url'] = '';

// List of files list columns. Available are: name, size, mtime, type
$config['kolab_files_list_cols'] = array('name', 'mtime', 'size');

// Name of the column to sort files list by
$config['kolab_files_sort_col'] = 'name';

// Order of the files list sort
$config['kolab_files_sort_order'] = 'asc';

// Number of concurent requests for searching and collections listing. Default: 1
$config['kolab_files_search_threads'] = 1;

// LDAP addressbook that would be searched for user names autocomplete.
// That should be an array refering to the $config['ldap_public'] array key
// or complete addressbook configuration array.
$config['kolab_files_users_source'] = 'kolab_addressbook';

// The LDAP attribute which will be used as ACL user identifier
$config['kolab_files_users_field'] = 'mail';

// The LDAP search filter will be combined with search queries
$config['kolab_files_users_filter'] = '';
?>
