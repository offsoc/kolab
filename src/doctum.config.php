<?php

use Doctum\Doctum;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->exclude('bootstrap')
    ->exclude('cache')
    ->exclude('database')
    ->exclude('node_modules')
    ->exclude('tests')
    ->exclude('vendor')
    ->in(__DIR__);

return new Doctum($iterator);
