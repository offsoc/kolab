import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue2'

export default defineConfig({
  resolve: {
    alias: {
      vue: 'vue/dist/vue.esm.js',
    },
  },
  plugins: [
    laravel({
      input: [
        'resources/js/user/app.js',
        'resources/js/admin/app.js',
        'resources/js/reseller/app.js',
        'resources/themes/app.scss',
        'resources/themes/default/app.scss',
      ],
      refresh: true,
    }),
    vue({
            template: {
                transformAssetUrls: {
                    // The Vue plugin will re-write asset URLs, when referenced
                    // in Single File Components, to point to the Laravel web
                    // server. Setting this to `null` allows the Laravel plugin
                    // to instead re-write asset URLs to point to the Vite
                    // server instead.
                    base: null,

                    // The Vue plugin will parse absolute URLs and treat them
                    // as absolute paths to files on disk. Setting this to
                    // `false` will leave absolute URLs un-touched so they can
                    // reference assets in the public directory as expected.
                    includeAbsolute: false,
                },
            },
        }),
  ],
})


// mix.options({
//     vue: {
//         compilerOptions: {
//             whitespace: 'condense'
//         }
//     }
// })

// // Prepare some resources before compilation
// mix.before(() => {
//     spawn('php', ['resources/build/before.php'], { stdio: 'inherit' })
// })

// // Compile the Vue/js resources
// mix.js('resources/js/user/app.js', 'public/js/user.js')
//     .js('resources/js/admin/app.js', 'public/js/admin.js')
//     .js('resources/js/reseller/app.js', 'public/js/reseller.js')
//     .vue()

// // Compile the themes/css resources
// glob.sync('resources/themes/*/', {}).forEach(fromDir => {
//     const toDir = fromDir.replace('resources/themes/', 'public/themes/')

//     mix.sass(fromDir + 'app.scss', toDir)
//         .sass(fromDir + 'document.scss', toDir);
// })
