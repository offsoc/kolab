<?php

$rootDir = __DIR__ . '/../..';

// Create build directory for js resources
echo "Build directory... ";

if (!file_exists("{$rootDir}/resources/build/js")) {
    mkdir("{$rootDir}/resources/build/js");
}

echo "OK\n";

// Write build timestamp to a file that is then included by the vue components
echo "Build timestamp... ";

file_put_contents(
    "{$rootDir}/resources/build/js/ts.js",
    sprintf("export default new Date('%s')", date('c'))
);

echo "OK\n";

// Convert UI localization into vue-i18n-compatible json format
echo "Client localization... ";

foreach (glob("{$rootDir}/resources/lang/*/ui.php") as $file) {
    $content = include $file;

    if (is_array($content)) {
        preg_match('|([a-z]+)/ui\.php$|', $file, $matches);

        $file = "{$rootDir}/resources/build/js/{$matches[1]}.json";
        $opts =  JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE;

        file_put_contents($file, json_encode($content, $opts));
    }
}

foreach (glob("{$rootDir}/resources/themes/*/lang/*/ui.php") as $file) {
    $content = include $file;

    if (is_array($content)) {
        preg_match('|([a-zA-Z]+)/lang/([a-z]+)/ui\.php$|', $file, $matches);

        $theme = $matches[1];
        $file = "{$rootDir}/resources/build/js/{$theme}-{$matches[2]}.json";
        $opts = JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE;

        file_put_contents($file, json_encode($content, $opts));
    }
}

echo "OK\n";

// Move some theme-specific resources from resources/themes/ to public/themes/
echo "Theme resources... ";

if (!file_exists("{$rootDir}/public/themes")) {
    mkdir("{$rootDir}/public/themes");
}

foreach (glob("{$rootDir}/resources/themes/*", GLOB_ONLYDIR) as $file) {
    $path = explode('/', $file);
    $theme = $path[count($path)-1];

    if (!file_exists("{$rootDir}/public/themes/{$theme}")) {
        mkdir("{$rootDir}/public/themes/{$theme}");
    }

    if (!file_exists("{$rootDir}/public/themes/{$theme}/images")) {
        mkdir("{$rootDir}/public/themes/{$theme}/images");
    }

    foreach (glob("{$file}/images/*") as $file) {
        $path = explode('/', $file);
        $image = $path[count($path)-1];
        copy($file, "{$rootDir}/public/themes/{$theme}/images/{$image}");
    }
}

echo "OK\n";
