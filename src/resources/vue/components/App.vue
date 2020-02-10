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
                        this.$store.state.authInfo = response.data
                        this.isLoading = false
                        this.$root.stopLoading()
                        this.$root.loginUser(token, false)
                    })
                    .catch(error => {
                        this.isLoading = false
                        this.$root.stopLoading()

                        if (error.response.status === 401 || error.response.status === 403) {
                            this.$root.logoutUser()
                        }
                    })
            } else {
                this.$root.stopLoading()
                this.isLoading = false
            }
        },
    }
</script>
