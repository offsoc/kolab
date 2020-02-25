<template>
    <div class="container" dusk="dashboard-component">
        <div v-if="!$root.isLoading" id="status-box" class="card">
            <div class="card-body">
                <div class="card-title">Status</div>
                <div class="card-text">
                    <ul style="list-style: none; padding: 0">
                        <li v-for="item in statusProcess">
                            <span v-if="item.state">&check;</span><span v-else>&cir;</span>
                            <router-link v-if="item.link" :to="{ path: item.link }">{{ item.title }}</router-link>
                            <span v-if="!item.link">{{ item.title }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <p id="dashboard-nav">
            <router-link class="link-profile" :to="{ name: 'profile' }">Your profile</router-link>
            <router-link class="link-domains" :to="{ name: 'domains' }">Domains</router-link>
            <router-link class="link-users" :to="{ name: 'users' }">User accounts</router-link>
        </p>
        <div v-if="!$root.isLoading" id="dashboard-box" class="card">
            <div class="card-body">
                <div class="card-title">Dashboard</div>
                <div class="card-text">
                    <pre>{{ data }}</pre>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                data: {},
                statusProcess: []
            }
        },
        mounted() {
            const authInfo = this.$store.state.isLoggedIn ? this.$store.state.authInfo : null

            if (authInfo) {
                this.data = authInfo
                this.parseStatusInfo(authInfo.statusInfo)
            } else {
                this.$root.startLoading()
                axios.get('/api/auth/info')
                    .then(response => {
                        this.data = response.data
                        this.$store.state.authInfo = response.data
                        this.parseStatusInfo(response.data.statusInfo)
                        this.$root.stopLoading()
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        methods: {
            // Displays account status information
            parseStatusInfo(info) {
                this.statusProcess = info.process

                // Update status process info every 10 seconds
                // FIXME: This probably should have some limit, or the interval
                //        should grow (well, until it could be done with websocket notifications)
                if (info.status != 'active') {
                    setTimeout(() => {
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
            }
        }
    }
</script>
