<template>
    <div class="container" dusk="dashboard-component">
        <user-search></user-search>
        <div id="dashboard-nav" class="mt-3">
            <router-link v-if="status.enableWallets" class="card link-wallet" :to="{ name: 'wallet' }">
                <svg-icon icon="wallet"></svg-icon><span>{{ $t('dashboard.wallet') }}</span>
                <span :class="'badge bg-' + (balance < 0 ? 'danger' : 'success')">{{ $root.price(balance) }}</span>
            </router-link>
            <router-link class="card link-invitations" :to="{ name: 'invitations' }">
                <svg-icon icon="envelope-open-text"></svg-icon><span>{{ $t('dashboard.invitations') }}</span>
            </router-link>
            <router-link class="card link-stats" :to="{ name: 'stats' }">
                <svg-icon icon="chart-line"></svg-icon><span>{{ $t('dashboard.stats') }}</span>
            </router-link>
        </div>
    </div>
</template>

<script>
    import UserSearch from '../Widgets/UserSearch'

    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faChartLine').definition,
        require('@fortawesome/free-solid-svg-icons/faEnvelopeOpenText').definition,
        require('@fortawesome/free-solid-svg-icons/faWallet').definition,
    )

    export default {
        components: {
            UserSearch
        },
        data() {
            return {
                status: {},
                balance: 0
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
                })
            }
        }
    }
</script>
