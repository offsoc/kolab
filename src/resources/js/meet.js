/**
 * Application code for the Meet UI
 */

import routes from './routes-meet.js'

window.routes = routes

require('./bootstrap')

import AppComponent from '../vue/Meet/App'
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
        }
    },
    methods: {
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
            if (!error.response) {
                // TODO: probably network connection error
            } else {
                this.errorPage(error.response.status, error.response.statusText)
            }
        }
    }
})

// Add a axios request interceptor
window.axios.interceptors.request.use(
    config => {
        // We're connecting to the API on the main domain
        config.url = window.config['app.url'] + config.url
        return config
    },
    error => {
        // Do something with request error
        return Promise.reject(error)
    }
)

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
