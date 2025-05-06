<?php

use Doctum\Doctum;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->exclude('bootstrap')
    ->exclude('cache')
    ->exclude('database')
    ->exclude('include')
    ->exclude('node_modules')
    ->exclude('vendor')
    ->in(__DIR__);

return new Doctum(
    $iterator,
    [
        'build_dir' => __DIR__ . '/../docs/build/%version%/',
        'cache_dir' => __DIR__ . '/cache/',
        'default_opened_level' => 1,
        // 'include_parent_data' => false,
    ]
);
