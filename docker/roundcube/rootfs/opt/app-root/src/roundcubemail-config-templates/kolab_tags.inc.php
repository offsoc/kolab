<?php

// Storage backend type (database, kolab, annotate)
if (getenv('KOLABOBJECTS_COMPAT_MODE') == "true") {
    $config['kolab_tags_driver'] = 'kolab';
} else {
    $config['kolab_tags_driver'] = 'annotate';
}
