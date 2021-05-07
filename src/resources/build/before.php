<?php

$rootDir = __DIR__ . '/../..';

// Create build directory for js resources
echo "Build directory...";

if (!file_exists("{$rootDir}/resources/build/js")) {
    mkdir("{$rootDir}/resources/build/js");
}

echo "OK\n";

// Write build timestamp to a file that is then included by the vue components
echo "Build timestamp...";

file_put_contents(
    "{$rootDir}/resources/build/js/ts.js",
    sprintf("export default new Date('%s')", date('c'))
);

echo "OK\n";

// Convert UI localization into vue-i18n-compatible json format
echo "Client localization...";

foreach (glob("{$rootDir}/resources/lang/*/ui.php") as $file) {
    $content = include $file;

    if (is_array($content)) {
        preg_match('|([a-z]+)/ui\.php$|', $file, $matches);

        $file = "{$rootDir}/resources/build/js/{$matches[1]}.json";
        $opts =  JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE;

        file_put_contents($file, json_encode($content, $opts));
    }
}

echo "OK\n";
