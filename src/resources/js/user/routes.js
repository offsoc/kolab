import DashboardComponent from '../../vue/Dashboard'
import DomainInfoComponent from '../../vue/Domain/Info'
import DomainListComponent from '../../vue/Domain/List'
import LoginComponent from '../../vue/Login'
import LogoutComponent from '../../vue/Logout'
import MeetComponent from '../../vue/Rooms'
import PageComponent from '../../vue/Page'
import PasswordResetComponent from '../../vue/PasswordReset'
import SignupComponent from '../../vue/Signup'
import UserInfoComponent from '../../vue/User/Info'
import UserListComponent from '../../vue/User/List'
import UserProfileComponent from '../../vue/User/Profile'
import UserProfileDeleteComponent from '../../vue/User/ProfileDelete'
import WalletComponent from '../../vue/Wallet'

// Here's a list of lazy-loaded components
// Note: you can pack multiple components into the same chunk, webpackChunkName
// is also used to get a sensible file name instead of numbers
const RoomComponent = () => import(/* webpackChunkName: "room" */ '../../vue/Meet/Room.vue')

const routes = [
    {
        path: '/dashboard',
        name: 'dashboard',
        component: DashboardComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/domain/:domain',
        name: 'domain',
        component: DomainInfoComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/domains',
        name: 'domains',
        component: DomainListComponent,
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
        path: '/password-reset/:code?',
        name: 'password-reset',
        component: PasswordResetComponent
    },
    {
        path: '/profile',
        name: 'profile',
        component: UserProfileComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/profile/delete',
        name: 'profile-delete',
        component: UserProfileDeleteComponent,
        meta: { requiresAuth: true }
    },
    {
        component: RoomComponent,
        name: 'room',
        path: '/meet/:room',
        meta: { loading: true }
    },
    {
        path: '/rooms',
        name: 'rooms',
        component: MeetComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/signup/:param?',
        alias: '/signup/voucher/:param',
        name: 'signup',
        component: SignupComponent
    },
    {
        path: '/user/:user',
        name: 'user',
        component: UserInfoComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/users',
        name: 'users',
        component: UserListComponent,
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
