import DashboardComponent from '../vue/Meet/Dashboard'
import Error404Component from '../vue/404'
import LoginComponent from '../vue/Login'
import LogoutComponent from '../vue/Logout'
import RoomComponent from '../vue/Meet/Room'

const routes = [
    {
        component: DashboardComponent,
        name: 'dashboard',
        path: '/meet'
    },
    {
        component: LoginComponent,
        name: 'login',
        path: '/meet/login'
    },
    {
        component: LogoutComponent,
        name: 'logout',
        path: '/logout'
    },
    {
        component: RoomComponent,
        name: 'room',
        path: '/meet/:room'
    },
    {
        component: Error404Component,
        name: '404',
        path: '*'
    }
]

export default routes
