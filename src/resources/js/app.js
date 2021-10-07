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
import { Tab } from 'bootstrap'
import { loadLangAsync, i18n } from './locale'

const loader = '<div class="app-loader"><div class="spinner-border" role="status"><span class="visually-hidden">Loading</span></div></div>'

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
    $('body').css('padding', 0) // remove padding added by unclosed modal
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
            localStorage.setItem('refreshToken', response.refresh_token)
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
                axios.post('/api/auth/refresh', {'refresh_token': response.refresh_token}).then(response => {
                    this.loginUser(response.data, false, true)
                })
            }, timeout * 1000)
        },
        // Set user state to "not logged in"
        logoutUser(redirect) {
            store.commit('logoutUser')
            localStorage.setItem('token', '')
            localStorage.setItem('refreshToken', '')
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
        addLoader(elem, small = true, style = null) {
            if (style) {
                $(elem).css(style)
            } else {
                $(elem).css('position', 'relative')
            }

            $(elem).append(small ? $(loader).addClass('small') : $(loader))
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
        tab(e) {
            e.preventDefault()
            new Tab(e.target).show()
        },
        errorPage(code, msg, hint) {
            // Until https://github.com/vuejs/vue-router/issues/977 is implemented
            // we can't really use router to display error page as it has two side
            // effects: it changes the URL and adds the error page to browser history.
            // For now we'll be replacing current view with error page "manually".

            if (!msg) msg = this.$te('error.' + code) ? this.$t('error.' + code) : this.$t('error.unknown')
            if (!hint) hint = ''

            const error_page = '<div id="error-page" class="error-page">'
                + `<div class="code">${code}</div><div class="message">${msg}</div><div class="hint">${hint}</div>`
                + '</div>'

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
            // TODO: Set locale argument according to the currently used locale
            return ((price || 0) / 100).toLocaleString('de-DE', { style: 'currency', currency: currency || 'CHF' })
        },
        priceLabel(cost, discount, currency) {
            let index = ''

            if (discount) {
                cost = Math.floor(cost * ((100 - discount) / 100))
                index = '\u00B9'
            }

            return this.price(cost, currency) + '/' + this.$t('wallet.month') + index
        },
        clickRecord(event) {
            if (!/^(a|button|svg|path)$/i.test(event.target.nodeName)) {
                $(event.target).closest('tr').find('a').trigger('click')
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
                return this.$t('status.deleted')
            }

            if (domain.isSuspended) {
                return this.$t('status.suspended')
            }

            if (!domain.isVerified || !domain.isLdapReady || !domain.isConfirmed) {
                return this.$t('status.notready')
            }

            return this.$t('status.active')
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
                return this.$t('status.deleted')
            }

            if (list.isSuspended) {
                return this.$t('status.suspended')
            }

            if (!list.isLdapReady) {
                return this.$t('status.notready')
            }

            return this.$t('status.active')
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
            let dialog = $('#support-dialog')[0]

            if (!dialog) {
                // FIXME: Find a nicer way of doing this
                SupportForm.i18n = i18n
                let form = new Vue(SupportForm)
                form.$mount($('<div>').appendTo(container)[0])
                form.$root = this
                form.$toast = this.$toast
                dialog = form.$el
            }

            dialog.__vue__.showDialog()
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
                return this.$t('status.deleted')
            }

            if (user.isSuspended) {
                return this.$t('status.suspended')
            }

            if (!user.isImapReady || !user.isLdapReady) {
                return this.$t('status.notready')
            }

            return this.$t('status.active')
        },
        // Append some wallet properties to the object
        userWalletProps(object) {
            let wallet = store.state.authInfo.accounts[0]

            if (!wallet) {
                wallet = store.state.authInfo.wallets[0]
            }

            if (wallet) {
                object.currency = wallet.currency
                if (wallet.discount) {
                    object.discount = wallet.discount
                    object.discount_description = wallet.discount_description
                }
            }
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
        // Do not display the error in a toast message, pass the error as-is
        if (error.config.ignoreErrors) {
            return Promise.reject(error)
        }

        if (error.config.onFinish) {
            error.config.onFinish()
        }

        let error_msg

        const status = error.response ? error.response.status : 200
        const data = error.response ? error.response.data : {}

        if (status == 422 && data.errors) {
            error_msg = app.$t('error.form')

            const modal = $('div.modal.show')

            $(modal.length ? modal : 'form').each((i, form) => {
                form = $(form)

                $.each(data.errors, (idx, msg) => {
                    const input_name = (form.data('validation-prefix') || form.find('form').first().data('validation-prefix') || '') + idx
                    let input = form.find('#' + input_name)

                    if (!input.length) {
                        input = form.find('[name="' + input_name + '"]');
                    }

                    if (input.length) {
                        // Create an error message
                        // API responses can use a string, array or object
                        let msg_text = ''
                        if (typeof(msg) !== 'string') {
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
        else if (data.status == 'error') {
            error_msg = data.message
        }
        else {
            error_msg = error.request ? error.request.statusText : error.message
        }

        app.$toast.error(error_msg || app.$t('error.server'))

        // Pass the error as-is
        return Promise.reject(error)
    }
)
