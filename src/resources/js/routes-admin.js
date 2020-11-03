import DashboardComponent from '../vue/Admin/Dashboard'
import DomainComponent from '../vue/Admin/Domain'
import LoginComponent from '../vue/Login'
import LogoutComponent from '../vue/Logout'
import PageComponent from '../vue/Page'
import UserComponent from '../vue/Admin/User'

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
