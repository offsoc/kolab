import DashboardComponent from '../vue/Meet/Dashboard'
import RoomComponent from '../vue/Meet/Room'

const routes = [
    {
        path: '/meet',
        name: 'dashboard',
        component: DashboardComponent
    },
    {
        path: '/meet/:room',
        component: RoomComponent
    }
]

export default routes
