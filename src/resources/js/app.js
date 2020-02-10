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
    methods: {
        // Clear (bootstrap) form validation state
        clearFormValidation(form) {
            $(form).find('.is-invalid').removeClass('is-invalid')
            $(form).find('.invalid-feedback').remove()
        },
        // Set user state to "logged in"
        loginUser(token, dashboard) {
            store.commit('loginUser')
            localStorage.setItem('token', token)
            axios.defaults.headers.common.Authorization = 'Bearer ' + token

            if (dashboard !== false) {
                router.push(store.state.afterLogin || { name: 'dashboard' })
            }

            store.state.afterLogin = null
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
        },
        errorPage(code, msg) {
            // Until https://github.com/vuejs/vue-router/issues/977 is implemented
            // we can't really use router to display error page as it has two side
            // effects: it changes the URL and adds the error page to browser history.
            // For now we'll be replacing current view with error page "manually".
            const map = {
                400: "Bad request",
                401: "Unauthorized",
                403: "Access denied",
                404: "Not found",
                405: "Method not allowed",
                500: "Internal server error"
            }

            if (!msg) msg = map[code] || "Unknown Error"

            const error_page = `<div id="error-page"><div class="code">${code}</div><div class="message">${msg}</div></div>`

            $('#app').children(':not(nav)').remove()
            $('#app').append(error_page)
        }
    }
})

Vue.use(VueToastr, {
    defaultPosition: 'toast-bottom-right',
    defaultTimeout: 50000
})
