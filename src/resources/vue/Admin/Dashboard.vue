<template>
    <div v-if="!$root.isLoading" class="container" dusk="dashboard-component">
        <div id="dashboard-nav"></div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                isReady: true
            }
        },
        mounted() {
            const authInfo = this.$store.state.isLoggedIn ? this.$store.state.authInfo : null

            if (authInfo) {

            } else {
                this.$root.startLoading()
                axios.get('/api/auth/info')
                    .then(response => {
                        this.$store.state.authInfo = response.data
                        this.$root.stopLoading()
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        methods: {
        }
    }
</script>
