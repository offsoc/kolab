import DashboardComponent from '../vue/Meet/Dashboard'
import RoomComponent from '../vue/Meet/Room'

const routes = [
    {
        path: '/',
        component: DashboardComponent
    },
    {
        path: '*',
        component: RoomComponent
    }
]

export default routes
