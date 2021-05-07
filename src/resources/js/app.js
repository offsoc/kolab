/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap')

import AppComponent from '../vue/App'
import MenuComponent from '../vue/Widgets/Menu'
import SupportForm from '../vue/Widgets/SupportForm'
import store from './store'
import { loadLangAsync, i18n } from './locale'

const loader = '<div class="app-loader"><div class="spinner-border" role="status"><span class="sr-only">Loading</span></div></div>'

let isLoading = 0

// Lock the UI with the 'loading...' element
const startLoading = () => {
    isLoading++
    let loading = $('#app > .app-loader').removeClass('fadeOut')
    if (!loading.length) {
        $('#app').append($(loader))
    }
}

// Hide "loading" overlay
const stopLoading = () => {
    if (isLoading > 0) {
        $('#app > .app-loader').addClass('fadeOut')
        isLoading--;
    }
}

let loadingRoute

// Note: This has to be before the app is created
// Note: You cannot use app inside of the function
window.router.beforeEach((to, from, next) => {
    // check if the route requires authentication and user is not logged in
    if (to.meta.requiresAuth && !store.state.isLoggedIn) {
        // remember the original request, to use after login
        store.state.afterLogin = to;

        // redirect to login page
        next({ name: 'login' })

        return
    }

    if (to.meta.loading) {
        startLoading()
        loadingRoute = to.name
    }

    next()
})

window.router.afterEach((to, from) => {
    if (to.name && loadingRoute === to.name) {
        stopLoading()
        loadingRoute = null
    }

    // When changing a page remove old:
    // - error page
    // - modal backdrop
    $('#error-page,.modal-backdrop.show').remove()
})

const app = new Vue({
    components: {
        AppComponent,
        MenuComponent,
    },
    i18n,
    store,
    router: window.router,
    data() {
        return {
            isUser: !window.isAdmin && !window.isReseller,
            appName: window.config['app.name'],
            appUrl: window.config['app.url'],
            themeDir: '/themes/' + window.config['app.theme']
        }
    },
    methods: {
        // Clear (bootstrap) form validation state
        clearFormValidation(form) {
            $(form).find('.is-invalid').removeClass('is-invalid')
            $(form).find('.invalid-feedback').remove()
        },
        hasPermission(type) {
            const authInfo = store.state.authInfo
            const key = 'enable' + type.charAt(0).toUpperCase() + type.slice(1)
            return !!(authInfo && authInfo.statusInfo[key])
        },
        hasRoute(name) {
            return this.$router.resolve({ name: name }).resolved.matched.length > 0
        },
        hasSKU(name) {
            const authInfo = store.state.authInfo
            return authInfo.statusInfo.skus && authInfo.statusInfo.skus.indexOf(name) != -1
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
        loginUser(response, dashboard, update) {
            if (!update) {
                store.commit('logoutUser') // destroy old state data
                store.commit('loginUser')
            }

            localStorage.setItem('token', response.access_token)
            axios.defaults.headers.common.Authorization = 'Bearer ' + response.access_token

            if (response.email) {
                store.state.authInfo = response
            }

            if (dashboard !== false) {
                this.$router.push(store.state.afterLogin || { name: 'dashboard' })
            }

            store.state.afterLogin = null

            // Refresh the token before it expires
            let timeout = response.expires_in || 0

            // We'll refresh 60 seconds before the token expires
            if (timeout > 60) {
                timeout -= 60
            }

            // TODO: We probably should try a few times in case of an error
            // TODO: We probably should prevent axios from doing any requests
            //       while the token is being refreshed

            this.refreshTimeout = setTimeout(() => {
                axios.post('/api/auth/refresh').then(response => {
                    this.loginUser(response.data, false, true)
                })
            }, timeout * 1000)
        },
        // Set user state to "not logged in"
        logoutUser(redirect) {
            store.commit('logoutUser')
            localStorage.setItem('token', '')
            delete axios.defaults.headers.common.Authorization

            if (redirect !== false) {
                this.$router.push({ name: 'login' })
            }

            clearTimeout(this.refreshTimeout)
        },
        logo(mode) {
            let src = this.appUrl + this.themeDir + '/images/logo_' + (mode || 'header') + '.png'

            return `<img src="${src}" alt="${this.appName}">`
        },
        // Display "loading" overlay inside of the specified element
        addLoader(elem, small = true) {
            $(elem).css({position: 'relative'}).append(small ? $(loader).addClass('small') : $(loader))
        },
        // Remove loader element added in addLoader()
        removeLoader(elem) {
            $(elem).find('.app-loader').remove()
        },
        startLoading,
        stopLoading,
        isLoading() {
            return isLoading > 0
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

            const error_page = `<div id="error-page" class="error-page"><div class="code">${code}</div><div class="message">${msg}</div></div>`

            $('#error-page').remove()
            $('#app').append(error_page)

            app.updateBodyClass('error')
        },
        errorHandler(error) {
            this.stopLoading()

            if (!error.response) {
                // TODO: probably network connection error
            } else if (error.response.status === 401) {
                // Remember requested route to come back to it after log in
                if (this.$route.meta.requiresAuth) {
                    store.state.afterLogin = this.$route
                    this.logoutUser()
                } else {
                    this.logoutUser(false)
                }
            } else {
                this.errorPage(error.response.status, error.response.statusText)
            }
        },
        downloadFile(url) {
            // TODO: This might not be a best way for big files as the content
            //       will be stored (temporarily) in browser memory
            // TODO: This method does not show the download progress in the browser
            //       but it could be implemented in the UI, axios has 'progress' property
            axios.get(url, { responseType: 'blob' })
                .then(response => {
                    const link = document.createElement('a')
                    const contentDisposition = response.headers['content-disposition']
                    let filename = 'unknown'

                    if (contentDisposition) {
                        const match = contentDisposition.match(/filename="(.+)"/);
                        if (match.length === 2) {
                            filename = match[1];
                        }
                    }

                    link.href = window.URL.createObjectURL(response.data)
                    link.download = filename
                    link.click()
                })
        },
        price(price, currency) {
            return ((price || 0) / 100).toLocaleString('de-DE', { style: 'currency', currency: currency || 'CHF' })
        },
        priceLabel(cost, discount) {
            let index = ''

            if (discount) {
                cost = Math.floor(cost * ((100 - discount) / 100))
                index = '\u00B9'
            }

            return this.price(cost) + '/month' + index
        },
        clickRecord(event) {
            if (!/^(a|button|svg|path)$/i.test(event.target.nodeName)) {
                let link = $(event.target).closest('tr').find('a')[0]
                if (link) {
                    link.click()
                }
            }
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
        distlistStatusClass(list) {
            if (list.isDeleted) {
                return 'text-muted'
            }

            if (list.isSuspended) {
                return 'text-warning'
            }

            if (!list.isLdapReady) {
                return 'text-danger'
            }

            return 'text-success'
        },
        distlistStatusText(list) {
            if (list.isDeleted) {
                return 'Deleted'
            }

            if (list.isSuspended) {
                return 'Suspended'
            }

            if (!list.isLdapReady) {
                return 'Not Ready'
            }

            return 'Active'
        },
        pageName(path) {
            let page = this.$route.path

            // check if it is a "menu page", find the page name
            // otherwise we'll use the real path as page name
            window.config.menu.every(item => {
                if (item.location == page && item.page) {
                    page = item.page
                    return false
                }
            })

            page = page.replace(/^\//, '')

            return page ? page : '404'
        },
        supportDialog(container) {
            let dialog = $('#support-dialog')

            // FIXME: Find a nicer way of doing this
            if (!dialog.length) {
                let form = new Vue(SupportForm)
                form.$mount($('<div>').appendTo(container)[0])
                form.$root = this
                form.$toast = this.$toast
                dialog = $(form.$el)
            }

            dialog.on('shown.bs.modal', () => {
                    dialog.find('input').first().focus()
                }).modal()
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
        },
        updateBodyClass(name) {
            // Add 'class' attribute to the body, different for each page
            // so, we can apply page-specific styles
            document.body.className = 'page-' + (name || this.pageName()).replace(/\/.*$/, '')
        }
    }
})

// Fetch the locale file and the start the app
loadLangAsync().then(() => app.$mount('#app'))

// Add a axios request interceptor
window.axios.interceptors.request.use(
    config => {
        // This is the only way I found to change configuration options
        // on a running application. We need this for browser testing.
        config.headers['X-Test-Payment-Provider'] = window.config.paymentProvider

        return config
    },
    error => {
        // Do something with request error
        return Promise.reject(error)
    }
)

// Add a axios response interceptor for general/validation error handler
window.axios.interceptors.response.use(
    response => {
        if (response.config.onFinish) {
            response.config.onFinish()
        }

        return response
    },
    error => {
        let error_msg
        let status = error.response ? error.response.status : 200

        // Do not display the error in a toast message, pass the error as-is
        if (error.config.ignoreErrors) {
            return Promise.reject(error)
        }

        if (error.config.onFinish) {
            error.config.onFinish()
        }

        if (error.response && status == 422) {
            error_msg = "Form validation error"

            const modal = $('div.modal.show')

            $(modal.length ? modal : 'form').each((i, form) => {
                form = $(form)

                $.each(error.response.data.errors || {}, (idx, msg) => {
                    const input_name = (form.data('validation-prefix') || form.find('form').first().data('validation-prefix') || '') + idx
                    let input = form.find('#' + input_name)

                    if (!input.length) {
                        input = form.find('[name="' + input_name + '"]');
                    }

                    if (input.length) {
                        // Create an error message
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
                            let controls = input.children(':not(:first-child)')

                            if (!controls.length && typeof msg == 'string') {
                                // this is an empty list (the main input only)
                                // and the error message is not an array
                                input.find('.main-input').addClass('is-invalid')
                            } else {
                                controls.each((index, element) => {
                                    if (msg[index]) {
                                        $(element).find('input').addClass('is-invalid')
                                    }
                                })
                            }

                            input.addClass('is-invalid').next('.invalid-feedback').remove()
                            input.after(feedback)
                        }
                        else {
                            // Standard form element
                            input.addClass('is-invalid')
                            input.parent().find('.invalid-feedback').remove()
                            input.parent().append(feedback)
                        }
                    }
                })

                form.find('.is-invalid:not(.listinput-widget)').first().focus()
            })
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
