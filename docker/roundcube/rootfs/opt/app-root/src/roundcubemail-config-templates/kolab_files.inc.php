<?php

// URL of kolab-chwala installation for public access
$config['kolab_files_url'] = getenv('KOLAB_FILES_URL') ?? 'https://' . ($_SERVER["HTTP_HOST"] ?? null) . '/chwala/';
// This is how the plugin does chwala api requests on the server
$config['kolab_files_server_url'] = getenv('KOLAB_FILES_SERVER_URL');

// List of files list columns. Available are: name, size, mtime, type
$config['kolab_files_list_cols'] = array('name', 'mtime', 'size');

// Name of the column to sort files list by
$config['kolab_files_sort_col'] = 'name';

// Order of the files list sort
$config['kolab_files_sort_order'] = 'asc';

// Number of concurent requests for searching and collections listing. Default: 1
$config['kolab_files_search_threads'] = 1;

?>
