import DashboardComponent from '../../vue/Reseller/Dashboard'
import DistlistComponent from '../../vue/Admin/Distlist'
import DomainComponent from '../../vue/Admin/Domain'
import InvitationsComponent from '../../vue/Reseller/Invitations'
import LoginComponent from '../../vue/Login'
import LogoutComponent from '../../vue/Logout'
import PageComponent from '../../vue/Page'
import ResourceComponent from '../../vue/Admin/Resource'
import SharedFolderComponent from '../../vue/Admin/SharedFolder'
import StatsComponent from '../../vue/Reseller/Stats'
import UserComponent from '../../vue/Admin/User'
import WalletComponent from '../../vue/Wallet'

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
