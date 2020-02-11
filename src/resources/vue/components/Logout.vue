<template></template>

<script>
    export default {
        mounted () {
            axios.post('/api/auth/logout')
                .then(response => {
                    this.$toastr('success', response.data.message)
                })

            this.$root.logoutUser()
        }
    }
</script>
