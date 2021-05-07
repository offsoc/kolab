
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

const { spawn } = require('child_process');
const glob = require('glob');
const mix = require('laravel-mix');

mix.webpackConfig({
    resolve: {
        alias: {
            'jquery$': 'jquery/dist/jquery.slim.js',
        }
    }
})

mix.js('resources/js/user/app.js', 'public/js/user.js').vue()
    .js('resources/js/admin/app.js', 'public/js/admin.js').vue()
    .js('resources/js/reseller/app.js', 'public/js/reseller.js').vue()

mix.before(() => {
    spawn('php', ['resources/build/before.php'], { stdio: 'inherit' })
})

glob.sync('resources/themes/*/', {}).forEach(fromDir => {
    const toDir = fromDir.replace('resources/themes/', 'public/themes/')

    mix.sass(fromDir + 'app.scss', toDir)
        .sass(fromDir + 'document.scss', toDir);
})
