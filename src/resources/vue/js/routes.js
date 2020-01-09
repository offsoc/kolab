import Vue from 'vue'
import VueRouter from 'vue-router'

Vue.use(VueRouter)

import DashboardComponent from '../components/Dashboard'
import Error404Component from '../components/404'
import LoginComponent from '../components/Login'
import LogoutComponent from '../components/Logout'
import PasswordResetComponent from '../components/PasswordReset'
import SignupComponent from '../components/Signup'

import store from './store'

const routes = [
    {
        path: '/',
        redirect: { name: 'login' }
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: DashboardComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/login',
        name: 'login',
        component: LoginComponent
    },
    {
        path: '/logout',
        name: 'logout',
        component: LogoutComponent
    },
    {
        path: '/password-reset/:code?',
        name: 'password-reset',
        component: PasswordResetComponent
    },
    {
        path: '/signup/:code?',
        name: 'signup',
        component: SignupComponent
    },
    {
        name: '404',
        path: '*',
        component: Error404Component
    }
]

const router = new VueRouter({
    mode: 'history',
    routes
})

router.beforeEach((to, from, next) => {

    // check if the route requires authentication and user is not logged in
    if (to.matched.some(route => route.meta.requiresAuth) && !store.state.isLoggedIn) {
        // redirect to login page
        next({ name: 'login' })
        return
    }

    // if logged in redirect to dashboard
    if (to.path === '/login' && store.state.isLoggedIn) {
        next({ name: 'dashboard' })
        return
    }

    next()
})

export default router
