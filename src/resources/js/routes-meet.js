import DashboardComponent from '../vue/Meet/Dashboard'
import LoginComponent from '../vue/Login'
import RoomComponent from '../vue/Meet/Room'

const routes = [
    {
        path: '/meet',
        name: 'dashboard',
        component: DashboardComponent
    },
    {
        path: '/meet/login',
        name: 'login',
        component: LoginComponent
    },
    {
        path: '/meet/:room',
        name: 'room',
        component: RoomComponent
    }
]

export default routes
