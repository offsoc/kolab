<template>
    <div class="container" dusk="dashboard-component">
        <status-component :status="status" @status-update="statusUpdate"></status-component>

        <div id="dashboard-nav">
            <router-link class="card link-settings" :to="{ name: 'settings' }">
                <svg-icon icon="user-gear"></svg-icon><span>{{ $t('dashboard.myaccount') }}</span>
            </router-link>
            <router-link v-if="status.enableDomains" class="card link-domains" :to="{ name: 'domains' }">
                <svg-icon icon="globe"></svg-icon><span>{{ $t('dashboard.domains') }}</span>
            </router-link>
            <router-link v-if="status.enableUsers" class="card link-users" :to="{ name: 'users' }">
                <svg-icon icon="user-group"></svg-icon><span>{{ $t('dashboard.users') }}</span>
            </router-link>
            <router-link v-if="status.enableDistlists" class="card link-distlists" :to="{ name: 'distlists' }">
                <svg-icon icon="users"></svg-icon><span>{{ $t('dashboard.distlists') }}</span>
                <span class="badge bg-primary">{{ $t('dashboard.beta') }}</span>
            </router-link>
            <router-link v-if="status.enableResources" class="card link-resources" :to="{ name: 'resources' }">
                <svg-icon icon="gear"></svg-icon><span>{{ $t('dashboard.resources') }}</span>
                <span class="badge bg-primary">{{ $t('dashboard.beta') }}</span>
            </router-link>
            <router-link v-if="status.enableFolders" class="card link-shared-folders" :to="{ name: 'shared-folders' }">
                <svg-icon icon="folder-open"></svg-icon><span>{{ $t('dashboard.shared-folders') }}</span>
                <span class="badge bg-primary">{{ $t('dashboard.beta') }}</span>
            </router-link>
            <router-link v-if="status.enableWallets" class="card link-wallet" :to="{ name: 'wallet' }">
                <svg-icon icon="wallet"></svg-icon><span>{{ $t('dashboard.wallet') }}</span>
                <span v-if="balance < 0" class="badge bg-danger">{{ $root.price(balance, currency) }}</span>
            </router-link>
            <router-link v-if="status.enableRooms" class="card link-chat" :to="{ name: 'rooms' }">
                <svg-icon icon="comments"></svg-icon><span>{{ $t('dashboard.chat') }}</span>
                <span class="badge bg-primary">{{ $t('dashboard.beta') }}</span>
            </router-link>
            <router-link v-if="status.enableFiles" class="card link-files" :to="{ name: 'files' }">
                <svg-icon icon="folder-closed"></svg-icon><span>{{ $t('dashboard.files') }}</span>
                <span class="badge bg-primary">{{ $t('dashboard.beta') }}</span>
            </router-link>
            <router-link v-if="status.enableSettings" class="card link-policies" :to="{ name: 'policies' }">
                <svg-icon icon="shield-halved"></svg-icon><span>{{ $t('dashboard.policies') }}</span>
            </router-link>
            <a v-if="webmailURL" class="card link-webmail" :href="webmailURL">
                <svg-icon icon="envelope"></svg-icon><span>{{ $t('dashboard.webmail') }}</span>
            </a>
            <router-link v-if="status.enableCompanionapps" class="card link-companionapp" :to="{ name: 'companions' }">
                <svg-icon icon="mobile-screen"></svg-icon><span>{{ $t('dashboard.companion') }}</span>
                <span class="badge bg-primary">{{ $t('dashboard.beta') }}</span>
            </router-link>
        </div>
    </div>
</template>

<script>
    import StatusComponent from './Widgets/Status'

    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faComments').definition,
        require('@fortawesome/free-solid-svg-icons/faDownload').definition,
        require('@fortawesome/free-solid-svg-icons/faEnvelope').definition,
        require('@fortawesome/free-solid-svg-icons/faFolderOpen').definition,
        require('@fortawesome/free-solid-svg-icons/faFolderClosed').definition,
        require('@fortawesome/free-solid-svg-icons/faGear').definition,
        require('@fortawesome/free-solid-svg-icons/faGlobe').definition,
        require('@fortawesome/free-solid-svg-icons/faMobileScreen').definition,
        require('@fortawesome/free-solid-svg-icons/faShieldHalved').definition,
        require('@fortawesome/free-solid-svg-icons/faSliders').definition,
        require('@fortawesome/free-solid-svg-icons/faUserGear').definition,
        require('@fortawesome/free-solid-svg-icons/faUsers').definition,
        require('@fortawesome/free-solid-svg-icons/faUserGroup').definition,
        require('@fortawesome/free-solid-svg-icons/faWallet').definition,
    )

    export default {
        components: {
            StatusComponent
        },
        data() {
            return {
                status: {},
                balance: 0,
                currency: '',
                webmailURL: window.config['app.webmail_url']
            }
        },
        mounted() {
            this.status = this.$root.authInfo.statusInfo
            this.getBalance(this.$root.authInfo)
        },
        methods: {
            getBalance(authInfo) {
                this.balance = 0;
                // TODO: currencies, multi-wallets, accounts
                authInfo.wallets.forEach(wallet => {
                    this.balance += wallet.balance
                    this.currency = wallet.currency
                })
            },
            statusUpdate(user) {
                this.status = Object.assign({}, this.status, user)
                this.$root.authInfo.statusInfo = this.status
            }
        }
    }
</script>
