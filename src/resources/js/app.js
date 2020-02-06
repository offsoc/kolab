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
import store from '../vue/js/store'
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
    store,
    router,
    data() {
        return {
            isLoading: true
        }
    },
    mounted() {
        this.$root.$on('clearFormValidation', (form) => {
            this.clearFormValidation(form)
        })
    },
    methods: {
        // Clear (bootstrap) form validation state
        clearFormValidation(form) {
            $(form).find('.is-invalid').removeClass('is-invalid')
            $(form).find('.invalid-feedback').remove()
        },
        // Set user state to "logged in"
        loginUser(token) {
            store.commit('loginUser')
            localStorage.setItem('token', token)
            axios.defaults.headers.common.Authorization = 'Bearer ' + token
            router.push({ name: 'dashboard' })
        },
        // Set user state to "not logged in"
        logoutUser() {
            store.commit('logoutUser')
            localStorage.setItem('token', '')
            delete axios.defaults.headers.common.Authorization
            router.push({ name: 'login' })
        },
        // Display "loading" overlay (to be used by route components)
        startLoading() {
            this.isLoading = true
            // Lock the UI with the 'loading...' element
            $('#app').append($('<div class="app-loader"><div class="spinner-border" role="status"><span class="sr-only">Loading</span></div></div>'))
        },
        // Hide "loading" overlay
        stopLoading() {
            $('#app > .app-loader').fadeOut()
            this.isLoading = false
        }
    }
})

Vue.use(VueToastr, {
    defaultPosition: 'toast-bottom-right',
    defaultTimeout: 50000
})
