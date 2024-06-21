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

    // TODO: Public dirs (glob patterns) should be in theme's meta.json

    foreach (['images', 'fonts'] as $subDir) {
        if (file_exists("{$rootDir}/resources/themes/{$theme}/{$subDir}")) {
            if (!file_exists("{$rootDir}/public/themes/{$theme}/{$subDir}")) {
                mkdir("{$rootDir}/public/themes/{$theme}/{$subDir}");
            }

            foreach (glob("{$rootDir}/resources/themes/{$theme}/{$subDir}/*") as $file) {
                $filename = pathinfo($file, PATHINFO_BASENAME);
                copy($file, "{$rootDir}/public/themes/{$theme}/{$subDir}/{$filename}");
            }
        }
    }
}

echo "OK\n";
