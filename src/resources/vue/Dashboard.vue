<template>
    <div class="container" dusk="dashboard-component">
        <status-component :status="status" @status-update="statusUpdate"></status-component>

        <div id="dashboard-nav">
            <router-link class="card link-profile" :to="{ name: 'profile' }">
                <svg-icon icon="user-cog"></svg-icon><span class="name">Your profile</span>
            </router-link>
            <router-link class="card link-domains" :to="{ name: 'domains' }">
                <svg-icon icon="globe"></svg-icon><span class="name">Domains</span>
            </router-link>
            <router-link class="card link-users" :to="{ name: 'users' }">
                <svg-icon icon="users"></svg-icon><span class="name">User accounts</span>
            </router-link>
            <router-link class="card link-wallet" :to="{ name: 'wallet' }">
                <svg-icon icon="wallet"></svg-icon><span class="name">Wallet</span>
                <span v-if="balance < 0" class="badge badge-danger">{{ $root.price(balance) }}</span>
            </router-link>
            <router-link class="card link-chat" :to="{ name: 'rooms' }">
                <svg-icon icon="comments"></svg-icon><span class="name">Video chat</span>
                <span class="badge badge-primary">beta</span>
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
                balance: 0
            }
        },
        mounted() {
            const authInfo = this.$store.state.isLoggedIn ? this.$store.state.authInfo : null

            if (authInfo) {
                this.status = authInfo.statusInfo
                this.getBalance(authInfo)
            } else {
                this.$root.startLoading()
                axios.get('/api/auth/info')
                    .then(response => {
                        this.$store.state.authInfo = response.data
                        this.status = response.data.statusInfo
                        this.getBalance(response.data)
                        this.$root.stopLoading()
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        methods: {
            getBalance(authInfo) {
                this.balance = 0;
                // TODO: currencies, multi-wallets, accounts
                authInfo.wallets.forEach(wallet => {
                    this.balance += wallet.balance
                })
            },
            statusUpdate(user) {
                this.status = Object.assign({}, this.status, user)
                this.$store.state.authInfo.statusInfo = this.status
            }
        }
    }
</script>
