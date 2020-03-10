import Vue from 'vue'
import VueRouter from 'vue-router'

Vue.use(VueRouter)

import DashboardComponent from '../vue/Dashboard'
import DomainInfoComponent from '../vue/Domain/Info'
import DomainListComponent from '../vue/Domain/List'
import Error404Component from '../vue/404'
import LoginComponent from '../vue/Login'
import LogoutComponent from '../vue/Logout'
import PasswordResetComponent from '../vue/PasswordReset'
import SignupComponent from '../vue/Signup'
import UserInfoComponent from '../vue/User/Info'
import UserListComponent from '../vue/User/List'
import UserProfileComponent from '../vue/User/Profile'
import UserProfileDeleteComponent from '../vue/User/ProfileDelete'

import store from './store'

const routes = [
    {
        path: '/',
        redirect: { name: 'dashboard' }
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: DashboardComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/domain/:domain',
        name: 'domain',
        component: DomainInfoComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/domains',
        name: 'domains',
        component: DomainListComponent,
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
        path: '/profile',
        name: 'profile',
        component: UserProfileComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/profile/delete',
        name: 'profile-delete',
        component: UserProfileDeleteComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/signup/:param?',
        name: 'signup',
        component: SignupComponent
    },
    {
        path: '/user/:user',
        name: 'user',
        component: UserInfoComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/users',
        name: 'users',
        component: UserListComponent,
        meta: { requiresAuth: true }
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
        // remember the original request, to use after login
        store.state.afterLogin = to;

        // redirect to login page
        next({ name: 'login' })

        return
    }

    next()
})

export default router
