/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap')

import AppComponent from '../vue/App'
import MenuComponent from '../vue/Widgets/Menu'
import store from './store'

const app = new Vue({
    el: '#app',
    components: {
        AppComponent,
        MenuComponent,
    },
    store,
    router: window.router,
    data() {
        return {
            isLoading: true,
            isAdmin: window.isAdmin
        }
    },
    methods: {
        // Clear (bootstrap) form validation state
        clearFormValidation(form) {
            $(form).find('.is-invalid').removeClass('is-invalid')
            $(form).find('.invalid-feedback').remove()
        },
        isController(wallet_id) {
            if (wallet_id && store.state.authInfo) {
                let i
                for (i = 0; i < store.state.authInfo.wallets.length; i++) {
                    if (wallet_id == store.state.authInfo.wallets[i].id) {
                        return true
                    }
                }
                for (i = 0; i < store.state.authInfo.accounts.length; i++) {
                    if (wallet_id == store.state.authInfo.accounts[i].id) {
                        return true
                    }
                }
            }

            return false
        },
        // Set user state to "logged in"
        loginUser(token, dashboard) {
            store.commit('logoutUser') // destroy old state data
            store.commit('loginUser')
            localStorage.setItem('token', token)
            axios.defaults.headers.common.Authorization = 'Bearer ' + token

            if (dashboard !== false) {
                this.$router.push(store.state.afterLogin || { name: 'dashboard' })
            }

            store.state.afterLogin = null
        },
        // Set user state to "not logged in"
        logoutUser() {
            store.commit('logoutUser')
            localStorage.setItem('token', '')
            delete axios.defaults.headers.common.Authorization
            this.$router.push({ name: 'login' })
        },
        // Display "loading" overlay (to be used by route components)
        startLoading() {
            this.isLoading = true
            // Lock the UI with the 'loading...' element
            let loading = $('#app > .app-loader').show()
            if (!loading.length) {
                $('#app').append($('<div class="app-loader"><div class="spinner-border" role="status"><span class="sr-only">Loading</span></div></div>'))
            }
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
        },
        errorHandler(error) {
            this.stopLoading()

            if (!error.response) {
                // TODO: probably network connection error
            } else if (error.response.status === 401) {
                this.logoutUser()
            } else {
                this.errorPage(error.response.status, error.response.statusText)
            }
        },
        price(price) {
            return (price/100).toLocaleString('de-DE', { style: 'currency', currency: 'CHF' })
        },
        priceLabel(cost, units = 1, discount) {
            let index = ''

            if (units < 0) {
                units = 1
            }

            if (discount) {
                cost = Math.floor(cost * ((100 - discount) / 100))
                index = '\u00B9'
            }

            return this.price(cost * units) + '/month' + index
        },
        domainStatusClass(domain) {
            if (domain.isDeleted) {
                return 'text-muted'
            }

            if (domain.isSuspended) {
                return 'text-warning'
            }

            if (!domain.isVerified || !domain.isLdapReady || !domain.isConfirmed) {
                return 'text-danger'
            }

            return 'text-success'
        },
        domainStatusText(domain) {
            if (domain.isDeleted) {
                return 'Deleted'
            }

            if (domain.isSuspended) {
                return 'Suspended'
            }

            if (!domain.isVerified || !domain.isLdapReady || !domain.isConfirmed) {
                return 'Not Ready'
            }

            return 'Active'
        },
        userStatusClass(user) {
            if (user.isDeleted) {
                return 'text-muted'
            }

            if (user.isSuspended) {
                return 'text-warning'
            }

            if (!user.isImapReady || !user.isLdapReady) {
                return 'text-danger'
            }

            return 'text-success'
        },
        userStatusText(user) {
            if (user.isDeleted) {
                return 'Deleted'
            }

            if (user.isSuspended) {
                return 'Suspended'
            }

            if (!user.isImapReady || !user.isLdapReady) {
                return 'Not Ready'
            }

            return 'Active'
        }
    }
})

// Add a axios response interceptor for general/validation error handler
window.axios.interceptors.response.use(
    response => {
        // Do nothing
        return response
    },
    error => {
        let error_msg
        let status = error.response ? error.response.status : 200

        if (error.response && status == 422) {
            error_msg = "Form validation error"

            $.each(error.response.data.errors || {}, (idx, msg) => {
                $('form').each((i, form) => {
                    const input_name = ($(form).data('validation-prefix') || '') + idx
                    const input = $('#' + input_name)

                    if (input.length) {
                        // Create an error message\
                        // API responses can use a string, array or object
                        let msg_text = ''
                        if ($.type(msg) !== 'string') {
                            $.each(msg, (index, str) => {
                                msg_text += str + ' '
                            })
                        }
                        else {
                            msg_text = msg
                        }

                        let feedback = $('<div class="invalid-feedback">').text(msg_text)

                        if (input.is('.list-input')) {
                            // List input widget
                            input.children(':not(:first-child)').each((index, element) => {
                                if (msg[index]) {
                                    $(element).find('input').addClass('is-invalid')
                                }
                            })

                            input.addClass('is-invalid').next('.invalid-feedback').remove()
                            input.after(feedback)
                        }
                        else {
                            // Standard form element
                            input.addClass('is-invalid')
                            input.parent().find('.invalid-feedback').remove()
                            input.parent().append(feedback)
                        }

                        return false
                    }
                });
            })

            $('form .is-invalid:not(.listinput-widget)').first().focus()
        }
        else if (error.response && error.response.data) {
            error_msg = error.response.data.message
        }
        else {
            error_msg = error.request ? error.request.statusText : error.message
        }

        app.$toast.error(error_msg || "Server Error")

        // Pass the error as-is
        return Promise.reject(error)
    }
)
