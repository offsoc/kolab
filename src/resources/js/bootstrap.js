/**
 * We'll load jQuery and the Bootstrap jQuery plugin which provides support
 * for JavaScript based Bootstrap features such as modals and tabs. This
 * code may be modified to fit the specific needs of your application.
 */

window.Popper = require('popper.js').default
window.$ = window.jQuery = require('jquery')

require('bootstrap')

/**
 * We'll load Vue, VueRouter and global components
 */

import FontAwesomeIcon from './fontawesome'
import Vue from 'vue'
import VueRouter from 'vue-router'
import Toast from '../vue/Widgets/Toast'
import store from './store'

window.Vue = Vue

Vue.component('svg-icon', FontAwesomeIcon)

const vTooltip = (el, binding) => {
    const t = []

    if (binding.modifiers.focus) t.push('focus')
    if (binding.modifiers.hover) t.push('hover')
    if (binding.modifiers.click) t.push('click')
    if (!t.length) t.push('hover')

    $(el).tooltip({
        title: binding.value,
        placement: binding.arg || 'top',
        trigger: t.join(' '),
        html: !!binding.modifiers.html,
    });
}

Vue.directive('tooltip', {
    bind: vTooltip,
    update: vTooltip,
    unbind (el) {
        $(el).tooltip('dispose')
    }
})

Vue.use(Toast)

Vue.use(VueRouter)

let vueRouterBase = '/'
try {
  let url = new URL(window.config['app.url'])
  vueRouterBase = url.pathname
} catch(e) {
    // ignore
}

window.router = new VueRouter({
    base: vueRouterBase,
    mode: 'history',
    routes: window.routes,
    scrollBehavior (to, from, savedPosition) {
        // Scroll the page to top, but not on Back action
        return savedPosition || { x: 0, y: 0 }
    }
})

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = require('axios')
axios.defaults.baseURL = vueRouterBase
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
