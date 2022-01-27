import LoginComponent from '../../vue/Login'
import LogoutComponent from '../../vue/Logout'
import PageComponent from '../../vue/Page'
import PasswordResetComponent from '../../vue/PasswordReset'
import SignupComponent from '../../vue/Signup'

// Here's a list of lazy-loaded components
// Note: you can pack multiple components into the same chunk, webpackChunkName
// is also used to get a sensible file name instead of numbers

const DashboardComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/Dashboard')
const DistlistInfoComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/Distlist/Info')
const DistlistListComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/Distlist/List')
const DomainInfoComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/Domain/Info')
const DomainListComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/Domain/List')
const MeetComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/Rooms')
const ResourceInfoComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/Resource/Info')
const ResourceListComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/Resource/List')
const SettingsComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/Settings')
const SharedFolderInfoComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/SharedFolder/Info')
const SharedFolderListComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/SharedFolder/List')
const UserInfoComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/User/Info')
const UserListComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/User/List')
const UserProfileComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/User/Profile')
const UserProfileDeleteComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/User/ProfileDelete')
const WalletComponent = () => import(/* webpackChunkName: "../user/pages" */ '../../vue/Wallet')
const RoomComponent = () => import(/* webpackChunkName: "../user/meet" */ '../../vue/Meet/Room.vue')

const routes = [
    {
        path: '/dashboard',
        name: 'dashboard',
        component: DashboardComponent,
        meta: { requiresAuth: true }
    },
    {
        path: '/distlist/:list',
        name: 'distlist',
        component: DistlistInfoComponent,
        meta: { requiresAuth: true, perm: 'distlists' }
    },
    {
        path: '/distlists',
        name: 'distlists',
        component: DistlistListComponent,
        meta: { requiresAuth: true, perm: 'distlists' }
    },
    {
        path: '/domain/:domain',
        name: 'domain',
        component: DomainInfoComponent,
        meta: { requiresAuth: true, perm: 'domains' }
    },
    {
        path: '/domains',
        name: 'domains',
        component: DomainListComponent,
        meta: { requiresAuth: true, perm: 'domains' }
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
        path: '/resource/:resource',
        name: 'resource',
        component: ResourceInfoComponent,
        meta: { requiresAuth: true, perm: 'resources' }
    },
    {
        path: '/resources',
        name: 'resources',
        component: ResourceListComponent,
        meta: { requiresAuth: true, perm: 'resources' }
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
        path: '/settings',
        name: 'settings',
        component: SettingsComponent,
        meta: { requiresAuth: true, perm: 'settings' }
    },
    {
        path: '/shared-folder/:folder',
        name: 'shared-folder',
        component: SharedFolderInfoComponent,
        meta: { requiresAuth: true, perm: 'folders' }
    },
    {
        path: '/shared-folders',
        name: 'shared-folders',
        component: SharedFolderListComponent,
        meta: { requiresAuth: true, perm: 'folders' }
    },
    {
        path: '/signup/invite/:param',
        name: 'signup-invite',
        component: SignupComponent
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
        meta: { requiresAuth: true, perm: 'users' }
    },
    {
        path: '/users',
        name: 'users',
        component: UserListComponent,
        meta: { requiresAuth: true, perm: 'users' }
    },
    {
        path: '/wallet',
        name: 'wallet',
        component: WalletComponent,
        meta: { requiresAuth: true, perm: 'wallets' }
    },
    {
        name: '404',
        path: '*',
        component: PageComponent
    }
]

export default routes
