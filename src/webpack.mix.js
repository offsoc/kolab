const mix = require('laravel-mix');

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

mix.webpackConfig({
    resolve: {
        alias: {
            'jquery$': 'jquery/dist/jquery.slim.js',
        }
    }
})

mix.js('resources/js/user.js', 'public/js')
    .js('resources/js/admin.js', 'public/js')
    .js('resources/js/meet.js', 'public/js')
    .sass('resources/sass/app.scss', 'public/css')
    .sass('resources/sass/document.scss', 'public/css');
