
/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

const fs = require('fs');
const glob = require('glob');
const mix = require('laravel-mix');

mix.webpackConfig({
    output: {
        publicPath: process.env.MIX_ASSET_PATH
    },
    resolve: {
        alias: {
            'jquery$': 'jquery/dist/jquery.slim.js',
        }
    }
})

mix.js('resources/js/user.js', 'public/js')
    .js('resources/js/admin.js', 'public/js')
    .js('resources/js/meet.js', 'public/js')

glob.sync('resources/themes/*/', {}).forEach(fromDir => {
    const toDir = fromDir.replace('resources/themes/', 'public/themes/')

    mix.sass(fromDir + 'app.scss', toDir)
        .sass(fromDir + 'document.scss', toDir);

    fs.stat(fromDir + 'images', {}, (err, stats) => {
        if (stats) {
            mix.copyDirectory(fromDir + 'images', toDir + 'images')
        }
    })
})
