import DashboardComponent from '../../vue/Admin/Dashboard'
import DistlistComponent from '../../vue/Admin/Distlist'
import DomainComponent from '../../vue/Admin/Domain'
import LoginComponent from '../../vue/Login'
import LogoutComponent from '../../vue/Logout'
import PageComponent from '../../vue/Page'
import ResourceComponent from '../../vue/Admin/Resource'
import StatsComponent from '../../vue/Admin/Stats'
import UserComponent from '../../vue/Admin/User'

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
