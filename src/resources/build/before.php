<?php

$rootDir = __DIR__ . '/../..';

// Write build timestamp to a file that is then included by the vue components
file_put_contents(
    "{$rootDir}/resources/js/ts.js",
    sprintf("export default new Date('%s')", date('c'))
);
