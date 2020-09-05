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
import VueRouter from 'vue-router'
import Toast from '../vue/Widgets/Toast'
import store from './store'

window.Vue = require('vue')

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

router.beforeEach((to, from, next) => {
    // check if the route requires authentication and user is not logged in
    if (to.matched.some(route => route.meta.requiresAuth) && !store.state.isLoggedIn) {
        // remember the original request, to use after login
        store.state.afterLogin = to;

        // redirect to login page
        next({ name: 'login' })

        return
    }

    next()
})

router.afterEach((to, from) => {
    // When changing a page remove old:
    // - error page
    // - modal backdrop
    $('#error-page,.modal-backdrop.show').remove()
})

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = require('axios')

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
