<template><div></div></template>

<script>
    export default {
        mounted () {
            axios.post('/api/auth/logout')
                .then(response => {
                    this.$toast.success(response.data.message)
                })
                .finally(() => {})

            this.$root.logoutUser()
        }
    }
</script>
