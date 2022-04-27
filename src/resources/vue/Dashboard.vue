<template>
    <div class="container" dusk="dashboard-component">
        <status-component :status="status" @status-update="statusUpdate"></status-component>

        <div id="dashboard-nav">
            <router-link class="card link-profile" :to="{ name: 'profile' }">
                <svg-icon icon="user-cog"></svg-icon><span>{{ $t('dashboard.profile') }}</span>
            </router-link>
            <router-link v-if="status.enableDomains" class="card link-domains" :to="{ name: 'domains' }">
                <svg-icon icon="globe"></svg-icon><span>{{ $t('dashboard.domains') }}</span>
            </router-link>
            <router-link v-if="status.enableUsers" class="card link-users" :to="{ name: 'users' }">
                <svg-icon icon="user-friends"></svg-icon><span>{{ $t('dashboard.users') }}</span>
            </router-link>
            <router-link v-if="status.enableDistlists" class="card link-distlists" :to="{ name: 'distlists' }">
                <svg-icon icon="users"></svg-icon><span>{{ $t('dashboard.distlists') }}</span>
                <span class="badge bg-primary">{{ $t('dashboard.beta') }}</span>
            </router-link>
            <router-link v-if="status.enableResources" class="card link-resources" :to="{ name: 'resources' }">
                <svg-icon icon="cog"></svg-icon><span>{{ $t('dashboard.resources') }}</span>
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
            <router-link v-if="$root.hasSKU('meet') && !$root.isDegraded()" class="card link-chat" :to="{ name: 'rooms' }">
                <svg-icon icon="comments"></svg-icon><span>{{ $t('dashboard.chat') }}</span>
                <span class="badge bg-primary">{{ $t('dashboard.beta') }}</span>
            </router-link>
            <router-link v-if="status.enableFiles && !$root.isDegraded()" class="card link-files" :to="{ name: 'files' }">
                <svg-icon icon="folder-open"></svg-icon><span>{{ $t('dashboard.files') }}</span>
                <span class="badge bg-primary">{{ $t('dashboard.beta') }}</span>
            </router-link>
            <router-link v-if="status.enableSettings" class="card link-settings" :to="{ name: 'settings' }">
                <svg-icon icon="sliders-h"></svg-icon><span>{{ $t('dashboard.settings') }}</span>
            </router-link>
            <a v-if="webmailURL" class="card link-webmail" :href="webmailURL">
                <svg-icon icon="envelope"></svg-icon><span>{{ $t('dashboard.webmail') }}</span>
            </a>
            <router-link v-if="status.enableCompanionapps" class="card link-companionapp" :to="{ name: 'companion' }">
                <svg-icon icon="mobile-alt"></svg-icon><span>{{ $t('dashboard.companion') }}</span>
                <span class="badge bg-primary">{{ $t('dashboard.beta') }}</span>
            </router-link>
        </div>
    </div>
</template>

<script>
    import StatusComponent from './Widgets/Status'

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
            const authInfo = this.$store.state.authInfo
            this.status = authInfo.statusInfo
            this.getBalance(authInfo)
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
                this.$store.state.authInfo.statusInfo = this.status
            }
        }
    }
</script>
