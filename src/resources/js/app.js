/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap')

window.Vue = require('vue')

import AppComponent from '../vue/components/App'
import MenuComponent from '../vue/components/Menu'
import router from '../vue/js/routes.js'
import VueToastr from '@deveodk/vue-toastr'

// Add a response interceptor for general/validation error handler
// This have to be before Vue and Router setup. Otherwise we would
// not be able to handle axios responses initiated from inside
// components created/mounted handlers (e.g. signup code verification link)
window.axios.interceptors.response.use(
    response => {
        // Do nothing
        return response
    },
    error => {
        var error_msg

        if (error.response && error.response.status == 422) {
            error_msg = "Form validation error"

            $.each(error.response.data.errors || {}, (idx, msg) => {
                $('form').each((i, form) => {
                    const input_name = ($(form).data('validation-prefix') || '') + idx
                    const input = $('#' + input_name)

                    if (input.length) {
                        input.addClass('is-invalid')
                            .parent().append($('<div class="invalid-feedback">')
                                .text($.type(msg) === 'string' ? msg : msg.join('<br>')))

                        return false
                    }
                });
            })

            $('form .is-invalid').first().focus()
        }
        else if (error.response && error.response.data) {
            error_msg = error.response.data.message
        }
        else {
            error_msg = error.request ? error.request.statusText : error.message
        }

        app.$toastr('error', error_msg || "Server Error", 'Error')

        // Pass the error as-is
        return Promise.reject(error)
    }
)

const app = new Vue({
    el: '#app',
    components: {
        'app-component': AppComponent,
        'menu-component': MenuComponent
    },
    router,
    mounted() {
        this.$root.$on('clearFormValidation', (form) => {
            this.clearFormValidation(form)
        })
    },
    methods: {
        clearFormValidation: form => {
            $(form).find('.is-invalid').removeClass('is-invalid')
            $(form).find('.invalid-feedback').remove()
        }
    }
})

Vue.use(VueToastr, {
    defaultPosition: 'toast-bottom-right',
    defaultTimeout: 50000
})
