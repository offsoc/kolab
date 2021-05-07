
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

mix.before(() => {
    spawn('php', ['resources/build/before.php'], { stdio: 'inherit' })
})

mix.js('resources/js/user.js', 'public/js').vue()
    .js('resources/js/admin.js', 'public/js').vue()

glob.sync('resources/themes/*/', {}).forEach(fromDir => {
    const toDir = fromDir.replace('resources/themes/', 'public/themes/')

    mix.sass(fromDir + 'app.scss', toDir)
        .sass(fromDir + 'document.scss', toDir);
})
