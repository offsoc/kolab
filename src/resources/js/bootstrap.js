/**
 * Import Cash (jQuery replacement)
 */

import $ from 'cash-dom'
window.$ = $

$.fn.focus = function() {
    if (this.length && this[0].focus) {
        this[0].focus()
    }
    return this
}

$.fn.click = function() {
    if (this.length && this[0].click) {
        this[0].click()
    }
    return this
}

/**
 * Load Vue, VueRouter and global components
 */

import Vue from 'vue'
import VueRouter from 'vue-router'
import Btn from '../vue/Widgets/Btn'
import BtnRouter from '../vue/Widgets/BtnRouter'
import Tabs from '../vue/Widgets/Tabs'
import Toast from '../vue/Widgets/Toast'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Tooltip } from 'bootstrap'

window.Vue = Vue

Vue.component('Btn', Btn)
Vue.component('BtnRouter', BtnRouter)
Vue.component('SvgIcon', FontAwesomeIcon)
Vue.component('Tabs', Tabs)

const vTooltip = (el, binding) => {
    let t = []

    if (binding.modifiers.focus) t.push('focus')
    if (binding.modifiers.hover) t.push('hover')
    if (binding.modifiers.click) t.push('click')
    if (!t.length) t.push('click')

    el.tooltip = new Tooltip(el, {
        title: binding.value,
        placement: binding.arg || 'top',
        trigger: t.join(' '),
        html: !!binding.modifiers.html
    })
}

Vue.directive('tooltip', {
    bind: vTooltip,
    update: vTooltip,
    unbind (el) {
        el.tooltip.dispose()
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
 * Load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = require('axios')
axios.defaults.baseURL = vueRouterBase
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// Register a few most common icons
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(
    require('@fortawesome/free-solid-svg-icons/faCheck').definition,
    require('@fortawesome/free-solid-svg-icons/faCircleInfo').definition,
    require('@fortawesome/free-solid-svg-icons/faPlus').definition,
    require('@fortawesome/free-solid-svg-icons/faMagnifyingGlass').definition,
    require('@fortawesome/free-solid-svg-icons/faTrashCan').definition,
    require('@fortawesome/free-solid-svg-icons/faUser').definition,
)
