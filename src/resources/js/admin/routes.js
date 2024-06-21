import LoginComponent from '../../vue/Login'
import LogoutComponent from '../../vue/Logout'
import PageComponent from '../../vue/Page'

// Here's a list of lazy-loaded components
// Note: you can pack multiple components into the same chunk, webpackChunkName
// is also used to get a sensible file name instead of numbers

const DashboardComponent = () => import(/* webpackChunkName: "../admin/pages" */ '../../vue/Admin/Dashboard')
const DistlistComponent = () => import(/* webpackChunkName: "../admin/pages" */ '../../vue/Admin/Distlist')
const DomainComponent = () => import(/* webpackChunkName: "../admin/pages" */ '../../vue/Admin/Domain')
const ResourceComponent = () => import(/* webpackChunkName: "../admin/pages" */ '../../vue/Admin/Resource')
const SharedFolderComponent = () => import(/* webpackChunkName: "../admin/pages" */ '../../vue/Admin/SharedFolder')
const StatsComponent = () => import(/* webpackChunkName: "../admin/pages" */ '../../vue/Admin/Stats')
const UserComponent = () => import(/* webpackChunkName: "../admin/pages" */ '../../vue/Admin/User')

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
        path: '/distlist/:list',
        name: 'distlist',
        component: DistlistComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/domain/:domain',
        name: 'domain',
        component: DomainComponent,
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
        path: '/resource/:resource',
        name: 'resource',
        component: ResourceComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/shared-folder/:folder',
        name: 'shared-folder',
        component: SharedFolderComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/stats',
        name: 'stats',
        component: StatsComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/user/:user',
        name: 'user',
        component: UserComponent,
        meta: { requiresAuth: true }
    },
    {
        name: '404',
        path: '*',
        component: PageComponent
    }
]

export default routes
