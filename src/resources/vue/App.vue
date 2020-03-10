<template>
    <router-view v-if="!isLoading"></router-view>
</template>

<script>
    export default {
        data() {
            return {
                isLoading: true
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
    }
</script>
