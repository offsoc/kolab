import Vue from 'vue'
import VueRouter from 'vue-router'

Vue.use(VueRouter)

import DashboardComponent from '../components/Dashboard'
import DomainInfoComponent from '../components/Domain/Info'
import DomainListComponent from '../components/Domain/List'
import Error404Component from '../components/404'
import LoginComponent from '../components/Login'
import LogoutComponent from '../components/Logout'
import PasswordResetComponent from '../components/PasswordReset'
import SignupComponent from '../components/Signup'
import UserInfoComponent from '../components/User/Info'
import UserListComponent from '../components/User/List'
import UserProfileComponent from '../components/User/Profile'
import UserProfileDeleteComponent from '../components/User/ProfileDelete'

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
