<template>

</template>

<script>
    import store from '../js/store'
    export default {
        mounted () {
            localStorage.removeItem('token')
            store.commit('logoutUser')
            this.$router.push({ name: 'login' })
        }
    }
</script>
