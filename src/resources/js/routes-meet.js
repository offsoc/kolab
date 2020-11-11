import PageComponent from '../vue/Page'
import LogoutComponent from '../vue/Logout'
import RoomComponent from '../vue/Meet/Room'

const routes = [
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
        component: PageComponent,
        name: '404',
        path: '*'
    }
]

export default routes
