/**
 * Application code for the Meet UI
 */

import routes from './routes-meet.js'

window.routes = routes

require('./bootstrap')

import AppComponent from '../vue/Meet/App'
import MenuComponent from '../vue/Meet/Widgets/Menu'
import store from './store'

const loader = '<div class="app-loader"><div class="spinner-border" role="status"><span class="sr-only">Loading</span></div></div>'

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
        }
    },
    methods: {
        // Clear (bootstrap) form validation state
        clearFormValidation(form) {
            $(form).find('.is-invalid').removeClass('is-invalid')
            $(form).find('.invalid-feedback').remove()
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

            $('#error-page').remove()
            $('#app').append(error_page)
        },
        errorHandler(error) {
            if (!error.response) {
                // TODO: probably network connection error
            } else {
                this.errorPage(error.response.status, error.response.statusText)
            }
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
        logoutUser(dashboard) {
            store.commit('logoutUser')
            localStorage.setItem('token', '')
            delete axios.defaults.headers.common.Authorization

            if (dashboard !== false) {
                this.$router.push({ name: 'dashboard' })
            }
        },
        // Display "loading" overlay inside of the specified element
        addLoader(elem) {
            $(elem).css({position: 'relative'}).append($(loader).addClass('small'))
        },
        // Remove loader element added in addLoader()
        removeLoader(elem) {
            $(elem).find('.app-loader').remove()
        },
        startLoading() {
            this.isLoading = true
            // Lock the UI with the 'loading...' element
            let loading = $('#app > .app-loader').show()
            if (!loading.length) {
                $('#app').append($(loader))
            }
        },
        // Hide "loading" overlay
        stopLoading() {
            $('#app > .app-loader').addClass('fadeOut')
            this.isLoading = false
        }
    }
})

// Register additional icons
import { library } from '@fortawesome/fontawesome-svg-core'

import {
    faDesktop,
    faExpand,
    faMicrophone,
    faPowerOff,
    faVideo
} from '@fortawesome/free-solid-svg-icons'

// Register only these icons we need
library.add(
    faDesktop,
    faExpand,
    faMicrophone,
    faPowerOff,
    faVideo
)
