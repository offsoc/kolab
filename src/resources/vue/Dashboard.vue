<template>
    <div v-if="!$root.isLoading" class="container" dusk="dashboard-component">
        <div v-if="!isReady" id="status-box" class="card">
            <div class="card-body">
                <div class="card-title">Account status: <span class="text-danger">Not ready</span></div>
                <div class="card-text">
                    <p>The process to create your account have not been completed yet.
                        Some features may be disabled or readonly.</p>
                    <ul class="status-list">
                        <li v-for="item in statusProcess" :key="item.label">
                            <svg-icon :icon="['far', item.state ? 'check-square' : 'square']"
                                      :class="item.state ? 'text-success' : 'text-muted'"
                            ></svg-icon>
                            <router-link v-if="item.link" :to="{ path: item.link }">{{ item.title }}</router-link>
                            <span v-if="!item.link">{{ item.title }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
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
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                isReady: true,
                statusProcess: [],
                request: null,
                balance: 0
            }
        },
        mounted() {
            const authInfo = this.$store.state.isLoggedIn ? this.$store.state.authInfo : null

            if (authInfo) {
                this.parseStatusInfo(authInfo.statusInfo)
                this.getBalance(authInfo)
            } else {
                this.$root.startLoading()
                axios.get('/api/auth/info')
                    .then(response => {
                        this.$store.state.authInfo = response.data
                        this.parseStatusInfo(response.data.statusInfo)
                        this.getBalance(response.data)
                        this.$root.stopLoading()
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        methods: {
            // Displays account status information
            parseStatusInfo(info) {
                this.statusProcess = info.process
                this.isReady = info.isReady

                // Update status process info every 10 seconds
                // FIXME: This probably should have some limit, or the interval
                //        should grow (well, until it could be done with websocket notifications)
                if (!info.isReady && !window.infoRequest) {
                    window.infoRequest = setTimeout(() => {
                        delete window.infoRequest
                        // Stop updates after user logged out
                        if (!this.$store.state.isLoggedIn) {
                            return;
                        }

                        axios.get('/api/auth/info')
                            .then(response => {
                                this.$store.state.authInfo = response.data
                                this.parseStatusInfo(response.data.statusInfo)
                            })
                            .catch(error => {
                                this.parseStatusInfo(info)
                            })
                    }, 10000);
                }
            },
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
