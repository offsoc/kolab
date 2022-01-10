import LoginComponent from '../../vue/Login'
import LogoutComponent from '../../vue/Logout'
import PageComponent from '../../vue/Page'

// Here's a list of lazy-loaded components
// Note: you can pack multiple components into the same chunk, webpackChunkName
// is also used to get a sensible file name instead of numbers

const DashboardComponent = () => import(/* webpackChunkName: "../reseller/pages" */ '../../vue/Reseller/Dashboard')
const DistlistComponent = () => import(/* webpackChunkName: "../reseller/pages" */ '../../vue/Admin/Distlist')
const DomainComponent = () => import(/* webpackChunkName: "../reseller/pages" */ '../../vue/Admin/Domain')
const InvitationsComponent = () => import(/* webpackChunkName: "../reseller/pages" */ '../../vue/Reseller/Invitations')
const ResourceComponent = () => import(/* webpackChunkName: "../reseller/pages" */ '../../vue/Admin/Resource')
const SharedFolderComponent = () => import(/* webpackChunkName: "../reseller/pages" */ '../../vue/Admin/SharedFolder')
const StatsComponent = () => import(/* webpackChunkName: "../reseller/pages" */ '../../vue/Reseller/Stats')
const UserComponent = () => import(/* webpackChunkName: "../reseller/pages" */ '../../vue/Admin/User')
const WalletComponent = () => import(/* webpackChunkName: "../reseller/pages" */ '../../vue/Wallet')

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
        path: '/invitations',
        name: 'invitations',
        component: InvitationsComponent,
        meta: { requiresAuth: true }
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
        path: '/wallet',
        name: 'wallet',
        component: WalletComponent,
        meta: { requiresAuth: true }
    },
    {
        name: '404',
        path: '*',
        component: PageComponent
    }
]

export default routes
