import DashboardComponent from '../vue/Meet/Dashboard'
import RoomComponent from '../vue/Meet/Room'

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: DashboardComponent
    },
    {
        path: '*',
        component: RoomComponent
    }
]

export default routes
