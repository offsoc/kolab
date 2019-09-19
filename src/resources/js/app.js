/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

window.Vue = require('vue');

import router from '../vue/js/routes.js';

import AppComponent from '../vue/components/AppComponent'

const app = new Vue({
    components: { AppComponent },
    router
}).$mount('#app');
