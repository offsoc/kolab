<template>
    <router-view v-if="!isLoading && !routerReloading"></router-view>
</template>

<script>
    export default {
        data() {
            return {
                isLoading: true,
                routerReloading: false
            }
        },
        mounted() {
            const token = localStorage.getItem('token')

            if (token) {
                this.$root.startLoading()
                axios.defaults.headers.common.Authorization = 'Bearer ' + token

                axios.get('/api/auth/info')
                    .then(response => {
                        this.isLoading = false
                        this.$root.stopLoading()
                        this.$root.loginUser(token, false)
                        this.$store.state.authInfo = response.data
                    })
                    .catch(error => {
                        // Release lock on the router-view, otherwise links (e.g. Logout) will not work
                        // FIXME: This causes dashboard to call /api/auth/info again
                        this.isLoading = false
                        this.$root.errorHandler(error)
                    })
            } else {
                this.$root.stopLoading()
                this.isLoading = false
            }
        },
        methods: {
            routerReload() {
                // Together with beforeRouteUpdate even on a route component
                // allows us to force reload the component. So it is possible
                // to jump from/to page that uses currently loaded component.
                this.routerReloading = true
                this.$nextTick().then(() => {
                    this.routerReloading = false
                })
            }
        }
    }
</script>
